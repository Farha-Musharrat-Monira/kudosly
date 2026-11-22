<?php

use App\Models\Plan;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Tests\Support\FakeStripeClient;

/**
 * Bind the fake Stripe client so no request ever leaves the process.
 *
 * The Stripe secret is faked as well, because the checkout controller refuses
 * to start a session without one. The fake key is a placeholder that is never
 * sent anywhere.
 */
beforeEach(function (): void {
    FakeStripeClient::bind();

    config()->set('cashier.secret', 'sk_test_fake_placeholder');
    config()->set('services.stripe.price_pro', 'price_kudosly_pro_test');
});

describe('authorization', function (): void {
    it('denies a guest', function (): void {
        $this->post(route('billing.checkout'))
            ->assertRedirect(route('login'));

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('denies an unverified account', function (): void {
        $user = unverifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'))
            ->assertRedirect(route('verification.notice'));

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });
});

describe('creating a checkout session', function (): void {
    it('creates a session and redirects to Stripe', function (): void {
        $user = verifiedUser();

        $response = $this->actingAs($user)->post(route('billing.checkout'));

        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_fake_session');

        expect(FakeStripeClient::callsTo('checkout.sessions', 'create'))->toHaveCount(1);
    });

    it('uses the configured Pro price and subscription mode', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)->post(route('billing.checkout'));

        $params = FakeStripeClient::paramsFor('checkout.sessions', 'create');

        expect($params['mode'])->toBe('subscription')
            ->and($params['line_items'])->toBe([['price' => 'price_kudosly_pro_test', 'quantity' => 1]]);
    });

    it('generates the redirect URLs on the server', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)->post(route('billing.checkout'));

        $params = FakeStripeClient::paramsFor('checkout.sessions', 'create');

        expect($params['success_url'])->toBe(route('billing', ['checkout' => 'success']))
            ->and($params['cancel_url'])->toBe(route('billing', ['checkout' => 'cancelled']));
    });

    it('creates a Stripe customer for a first-time buyer', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)->post(route('billing.checkout'));

        expect(FakeStripeClient::callsTo('customers', 'create'))->toHaveCount(1)
            ->and(FakeStripeClient::paramsFor('customers', 'create')['email'])->toBe($user->email);

        // The customer id is stored against the account.
        expect($user->fresh()->stripe_id)->toBe('cus_fake_1');
    });

    it('reuses the existing Stripe customer for a returning customer', function (): void {
        $user = verifiedUser();
        $user->forceFill(['stripe_id' => 'cus_existing'])->save();

        $this->actingAs($user)->post(route('billing.checkout'));

        // No customer is created a second time.
        expect(FakeStripeClient::callsTo('customers', 'create'))->toBeEmpty()
            ->and(FakeStripeClient::callsTo('customers', 'retrieve'))->toHaveCount(1)
            ->and(FakeStripeClient::paramsFor('checkout.sessions', 'create')['customer'])->toBe('cus_existing');
    });

    it('sends the billing email to Stripe', function (): void {
        $user = verifiedUser(['email' => 'owner@example.com']);
        $user->forceFill(['billing_email' => 'billing@example.com'])->save();

        $this->actingAs($user->fresh())->post(route('billing.checkout'));

        expect(FakeStripeClient::paramsFor('customers', 'create')['email'])->toBe('owner@example.com');
    });
});

describe('failing safely', function (): void {
    it('refuses to start a session when the Stripe secret is missing', function (): void {
        config()->set('cashier.secret', null);

        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'))
            ->assertRedirect()
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('refuses to start a session when the Stripe secret is an empty string', function (): void {
        config()->set('cashier.secret', '');

        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('refuses to start a session when the Pro price is missing', function (): void {
        config()->set('services.stripe.price_pro', null);

        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('refuses to start a session when the Pro price is an empty string', function (): void {
        config()->set('services.stripe.price_pro', '');

        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('does not grant Pro access when checkout cannot start', function (): void {
        config()->set('services.stripe.price_pro', null);

        $user = verifiedUser();

        $this->actingAs($user)->post(route('billing.checkout'));

        expect($user->fresh()->subscribedToPro())->toBeFalse()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free')
            ->and($user->fresh()->spaceQuota())->toBe(3);
    });

    it('leaves the account untouched when Stripe refuses the request', function (): void {
        FakeStripeClient::failWith(new StripeInvalidRequestException('No such price'));

        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'))
            ->assertSessionHasErrors('billing');

        expect($user->fresh()->subscribedToPro())->toBeFalse()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free')
            ->and($user->fresh()->subscriptions()->count())->toBe(0);
    });
});

describe('duplicate subscriptions', function (): void {
    it('refuses to start a second session for an active Pro subscriber', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user)
            ->post(route('billing.checkout'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });

    it('allows a new session when the earlier subscription has expired', function (): void {
        $user = verifiedUser();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_kudosly_pro_test',
            'ends_at' => now()->subDay(),
        ]);

        SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'stripe_price' => 'price_kudosly_pro_test',
        ]);

        expect($user->subscribedToPro())->toBeFalse();

        $this->actingAs($user)
            ->post(route('billing.checkout'))
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_fake_session');

        expect(FakeStripeClient::callsTo('checkout.sessions', 'create'))->toHaveCount(1);
    });

    it('refuses a second session for a subscriber inside the grace period', function (): void {
        $user = verifiedUser();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'stripe_status' => 'active',
            'stripe_price' => 'price_kudosly_pro_test',
            'ends_at' => now()->addDays(10),
        ]);

        SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'stripe_price' => 'price_kudosly_pro_test',
        ]);

        expect($user->subscribedToPro())->toBeTrue();

        $this->actingAs($user)
            ->post(route('billing.checkout'))
            ->assertSessionHasErrors('billing');

        expect(FakeStripeClient::$calls)->toBeEmpty();
    });
});

describe('entitlement safety', function (): void {
    it('does not grant Pro access just because checkout was started', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)->post(route('billing.checkout'));

        // The session exists on Stripe, but no subscription does locally, so
        // the account is still on Free.
        expect($user->fresh()->subscribedToPro())->toBeFalse()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free')
            ->and($user->fresh()->spaceQuota())->toBe(3)
            ->and($user->fresh()->testimonialQuota())->toBe(100)
            ->and($user->fresh()->subscriptions()->count())->toBe(0);
    });

    it('does not grant Pro access from a stored Pro plan id alone', function (): void {
        $user = verifiedUser();
        $user->forceFill(['plan_id' => Plan::pro()->getKey()])->save();

        $this->actingAs($user)->post(route('billing.checkout'));

        expect($user->fresh()->subscribedToPro())->toBeFalse()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free');
    });
});

describe('not trusting the request', function (): void {
    it('ignores a price submitted with the request', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'), [
                'price' => 'price_attacker_supplied',
                'stripe_price' => 'price_attacker_supplied',
                'price_id' => 'price_attacker_supplied',
            ])
            ->assertRedirect();

        $params = FakeStripeClient::paramsFor('checkout.sessions', 'create');

        expect($params['line_items'])->toBe([['price' => 'price_kudosly_pro_test', 'quantity' => 1]])
            ->and(json_encode($params))->not->toContain('price_attacker_supplied');
    });

    it('ignores a customer submitted with the request', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'), ['customer' => 'cus_attacker'])
            ->assertRedirect();

        expect(FakeStripeClient::paramsFor('checkout.sessions', 'create')['customer'])->toBe('cus_fake_1');
    });

    it('ignores subscription metadata submitted with the request', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'), [
                'subscription_data' => ['metadata' => ['is_pro' => 'true']],
                'metadata' => ['plan' => 'pro'],
            ])
            ->assertRedirect();

        $params = FakeStripeClient::paramsFor('checkout.sessions', 'create');

        // Cashier sets its own subscription metadata; nothing the browser sent
        // can mark the subscription as Pro.
        expect($params['subscription_data']['metadata'])->toBe([
            'name' => 'default',
            'type' => 'default',
            'is_on_session_checkout' => true,
        ]);
    });

    it('ignores a type submitted with the request', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->post(route('billing.checkout'), ['type' => 'premium'])
            ->assertRedirect();

        $params = FakeStripeClient::paramsFor('checkout.sessions', 'create');

        expect($params['subscription_data']['metadata']['type'])->toBe('default');
    });
});

describe('the billing page', function (): void {
    it('offers a working checkout form when billing is configured', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Upgrade to Pro')
            ->assertSeeHtml('action="'.route('billing.checkout').'"')
            ->assertSeeHtml('name="_token"');
    });

    it('disables checkout and explains why when the Stripe secret is missing', function (): void {
        config()->set('cashier.secret', null);

        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Checkout unavailable')
            ->assertSee('Billing is not configured on this server yet.')
            ->assertDontSee('Upgrade to Pro')
            ->assertSeeHtml('disabled="disabled"');
    });

    it('disables checkout and explains why when the Pro price is missing', function (): void {
        config()->set('services.stripe.price_pro', null);

        $user = verifiedUser();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Checkout unavailable')
            ->assertSee('The Pro plan price is not configured yet.')
            ->assertDontSee('Upgrade to Pro');
    });

    it('shows the Pro state instead of a checkout button for a Pro subscriber', function (): void {
        $user = User::factory()->proPlan()->create();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('You are on the Pro plan')
            ->assertDontSee('Upgrade to Pro');
    });

    it('never exposes the Stripe secret or the Pro price in the rendered page', function (): void {
        config()->set('cashier.secret', 'sk_test_super_secret_value');

        $user = verifiedUser();

        $response = $this->actingAs($user)->get(route('billing'));

        $response->assertOk()
            ->assertDontSee('sk_test_super_secret_value')
            ->assertDontSee('price_kudosly_pro_test');
    });
});

describe('no network access', function (): void {
    it('performs every Stripe call through the fake', function (): void {
        $user = verifiedUser();

        $this->actingAs($user)->post(route('billing.checkout'));

        // Every recorded call went through a fake service, so no request
        // could have reached Stripe.
        foreach (FakeStripeClient::$calls as $call) {
            expect($call['service'])->toBeIn(['customers', 'checkout.sessions']);
        }

        expect(FakeStripeClient::$calls)->not->toBeEmpty();
    });
});
