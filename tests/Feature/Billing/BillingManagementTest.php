<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Tests\Support\FakeStripeClient;

/**
 * The billing portal and the cancellation flow.
 *
 * Both act on the authenticated account's own Stripe customer and
 * subscription, read from the database. Nothing about the subscription is
 * taken from the request.
 */
beforeEach(function (): void {
    FakeStripeClient::bind();

    config()->set('cashier.secret', 'sk_test_fake_placeholder');
    config()->set('services.stripe.price_pro', 'price_kudosly_pro_test');
});

describe('authorization', function (): void {
    it('denies a guest the billing portal', function (): void {
        $this->post(route('billing.portal'))
            ->assertRedirect(route('login'));

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('denies an unverified account the billing portal', function (): void {
        $user = unverifiedUser();

        $this->actingAs($user)
            ->post(route('billing.portal'))
            ->assertRedirect(route('verification.notice'));

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('denies a guest the cancellation', function (): void {
        $this->post(route('billing.cancel'))
            ->assertRedirect(route('login'));

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('denies an unverified account the cancellation', function (): void {
        $user = unverifiedUser();

        $this->actingAs($user)
            ->post(route('billing.cancel'))
            ->assertRedirect(route('verification.notice'));

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });
});

describe('the billing portal', function (): void {
    it('redirects to the Stripe billing portal', function (): void {
        $user = User::factory()->proPlan()->create();
        $user->forceFill(['stripe_id' => 'cus_portal_1'])->save();

        $this->actingAs($user->fresh())
            ->post(route('billing.portal'))
            ->assertRedirect('https://billing.stripe.com/session/bps_fake_session');

        expect(FakeStripeClient::callsTo('billingPortal.sessions', 'create'))->toHaveCount(1);
    });

    it('opens the portal for the authenticated account own customer', function (): void {
        $user = User::factory()->proPlan()->create();
        $user->forceFill(['stripe_id' => 'cus_portal_1'])->save();

        $this->actingAs($user->fresh())->post(route('billing.portal'));

        $params = FakeStripeClient::paramsFor('billingPortal.sessions', 'create');

        expect($params['customer'])->toBe('cus_portal_1');
    });

    it('generates the return url on the server', function (): void {
        $user = User::factory()->proPlan()->create();
        $user->forceFill(['stripe_id' => 'cus_portal_1'])->save();

        $this->actingAs($user->fresh())->post(route('billing.portal'));

        $params = FakeStripeClient::paramsFor('billingPortal.sessions', 'create');

        expect($params['return_url'])->toBe(route('billing'));
    });

    it('refuses to open a portal for an account with no Stripe customer', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.portal'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('refuses to open a portal when billing is not configured', function (): void {
        config()->set('cashier.secret', null);

        $user = User::factory()->proPlan()->create();
        $user->forceFill(['stripe_id' => 'cus_portal_1'])->save();

        $this->actingAs($user->fresh())
            ->post(route('billing.portal'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('leaves the account untouched when Stripe refuses the request', function (): void {
        FakeStripeClient::failWith(new StripeInvalidRequestException('No such customer'));

        $user = User::factory()->proPlan()->create();
        $user->forceFill(['stripe_id' => 'cus_portal_1'])->save();

        $this->actingAs($user->fresh())
            ->post(route('billing.portal'))
            ->assertSessionHasErrors('billing');

        expect($user->fresh()->subscribedToPro())->toBeTrue();
    });
});

describe('cancelling a subscription', function (): void {
    it('cancels the subscription at the end of the paid period', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())
            ->post(route('billing.cancel'))
            ->assertRedirect()
            ->assertSessionHas('billing_status', 'cancelled');

        expect(FakeStripeClient::callsTo('subscriptions', 'update'))->toHaveCount(1);
    });

    it('asks Stripe to stop the renewal rather than cancelling outright', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())->post(route('billing.cancel'));

        $params = FakeStripeClient::paramsFor('subscriptions', 'update');

        expect($params['cancel_at_period_end'])->toBeTrue();
    });

    it('cancels the authenticated account own subscription', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())->post(route('billing.cancel'));

        $params = FakeStripeClient::paramsFor('subscriptions', 'update');

        expect($params['id'])->toBe($user->fresh()->subscriptions()->sole()->stripe_id);
    });

    it('keeps Pro access until the paid period ends', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())->post(route('billing.cancel'));

        $subscription = $user->fresh()->subscriptions()->sole();

        expect($subscription->ends_at)->not->toBeNull()
            ->and($subscription->ends_at->isFuture())->toBeTrue()
            ->and($user->fresh()->onGracePeriod())->toBeTrue()
            ->and($user->fresh()->subscribedToPro())->toBeTrue()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('pro');
    });

    it('never cancels another account subscription', function (): void {
        $other = User::factory()->proPlan()->create();

        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.cancel'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty()
            ->and($other->fresh()->subscriptions()->sole()->ends_at)->toBeNull()
            ->and($other->fresh()->subscribedToPro())->toBeTrue();
    });

    it('refuses to cancel an account with no subscription', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.cancel'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('refuses to cancel a subscription that is already cancelled', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())->post(route('billing.cancel'));

        $this->actingAs($user->fresh())
            ->post(route('billing.cancel'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::callsTo('subscriptions', 'update'))->toHaveCount(1);
    });

    it('keeps the subscription running when Stripe refuses the request', function (): void {
        FakeStripeClient::failWith(new StripeInvalidRequestException('No such subscription'));

        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())
            ->post(route('billing.cancel'))
            ->assertSessionHasErrors('billing');

        // Nothing was cancelled, so the account keeps its Pro access.
        expect($user->fresh()->subscriptions()->sole()->ends_at)->toBeNull()
            ->and($user->fresh()->subscribedToPro())->toBeTrue()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('pro');
    });
});

describe('the billing page', function (): void {
    it('offers the upgrade to a Free account', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Upgrade to Pro');
    });

    it('offers the portal and the cancellation to an active subscriber', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Manage billing')
            ->assertSee('Cancel subscription');
    });

    it('does not offer the cancellation once the subscription is cancelled', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())->post(route('billing.cancel'));

        $this->actingAs($user->fresh())
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Subscription cancelled')
            ->assertDontSee('Cancel subscription');
    });

    it('requires an authenticated account', function (): void {
        $this->get(route('billing'))
            ->assertRedirect(route('login'));
    });

    it('shows the current plan, its price and the billing interval', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Pro')
            ->assertSee('$999.00/mo')
            ->assertSee('Billed monthly in USD')
            ->assertSee('Billing interval')
            ->assertSee('Monthly');
    });

    it('does not present the cancellation date as a renewal date', function (): void {
        $user = User::factory()->proPlan()->create();

        // A subscription that is on its way out carries the date its access
        // ends. That date is the end of the paid period, never the date the
        // subscription renews.
        $subscription = $user->fresh()->subscriptions()->sole();
        $subscription->forceFill(['ends_at' => now()->addDays(9)])->save();

        $this->actingAs($user->fresh())
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Access until')
            ->assertSee(now()->addDays(9)->format('j M Y'))
            ->assertDontSee('Next billing date');
    });

    it('sends an active subscriber to the portal for the renewal date', function (): void {
        $user = User::factory()->proPlan()->create();

        // A renewing subscription carries no cancellation date at all, so the
        // page must not invent one to fill the renewal row.
        expect($user->fresh()->subscriptions()->sole()->ends_at)->toBeNull();

        $this->actingAs($user->fresh())
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Renewal')
            ->assertSee('Manage billing to see your renewal date')
            ->assertDontSee('Next billing date');
    });

    it('shows that a Free account has no subscription', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('No card required')
            ->assertSee('There is no subscription and no card on file');
    });

    it('shows the space usage and the remaining capacity', function (): void {
        $user = verifiedUser();

        Space::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user->fresh())
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Spaces')
            ->assertSee('1 of 3 spaces remaining');
    });

    it('shows the testimonial usage against the plan limit', function (): void {
        $user = verifiedUser();

        $space = Space::factory()->create(['user_id' => $user->id]);

        Testimonial::factory()->count(5)->create([
            'user_id' => $user->id,
            'space_id' => $space->id,
        ]);

        $this->actingAs($user->fresh())
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Testimonials')
            // 5 used against a single space worth of the Free quota.
            ->assertSee('95 remaining across your spaces');
    });

    it('shows the Pro limits in the plan comparison', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('3 spaces')
            ->assertSee('100 testimonials per space')
            ->assertSee('25 spaces')
            ->assertSee('1000 testimonials per space');
    });

    it('marks the current plan in the comparison', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Your plan');
    });

    it('reports the return from checkout without claiming the payment was confirmed', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing', ['checkout' => 'success']))
            ->assertOk()
            ->assertSee('Checkout completed')
            ->assertSee('You are still on the Free plan')
            // The query parameter alone proves nothing about the payment, so
            // the page must not claim that the money was received.
            ->assertDontSee('Payment received')
            ->assertDontSee('Thank you');
    });

    it('does not report a checkout completion that was never started', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertDontSee('Checkout completed')
            ->assertDontSee('Payment received');
    });

    it('reports a cancelled checkout without changing the plan', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing', ['checkout' => 'cancelled']))
            ->assertOk()
            ->assertSee('Checkout cancelled')
            ->assertSee('Nothing was charged and your plan is unchanged');
    });

    it('reports a scheduled cancellation', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())->post(route('billing.cancel'));

        $this->actingAs($user->fresh())
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Cancellation scheduled')
            ->assertSee('Pro access continues until the period you have paid for ends');
    });

    it('never offers the upgrade to an active Pro subscriber', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user->fresh())
            ->get(route('billing'))
            ->assertOk()
            ->assertDontSee('Upgrade to Pro')
            ->assertSeeHtml('action="'.route('billing.portal').'"')
            ->assertSeeHtml('action="'.route('billing.cancel').'"');
    });

    it('shows an empty state when the account has no spaces', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('No spaces yet');
    });

    it('exposes the usage bars to assistive technology', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSeeHtml('role="progressbar"')
            ->assertSeeHtml('aria-valuenow="0"');
    });
});
