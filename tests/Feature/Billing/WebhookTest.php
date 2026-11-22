<?php

use App\Models\Plan;
use App\Models\Space;
use App\Models\StripeWebhookEvent;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\StripeWebhook;

/**
 * Deliver a signed webhook to the endpoint.
 */
function deliver(array $payload, ?string $signature = null): TestResponse
{
    $body = StripeWebhook::body($payload);

    return test()->call(
        'POST',
        route('cashier.webhook'),
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => $signature ?? StripeWebhook::signature($payload),
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    );
}

/**
 * Create an account that already has a Stripe customer.
 *
 * The Stripe customer identifier is not fillable, so it is written directly
 * the same way the application writes it.
 */
function customer(string $stripeId = 'cus_test_123'): User
{
    $user = verifiedUser();

    $user->forceFill(['stripe_id' => $stripeId])->save();

    return $user;
}

beforeEach(function (): void {
    config()->set('cashier.webhook.secret', StripeWebhook::SECRET);
    config()->set('cashier.webhook.tolerance', 300);
    config()->set('services.stripe.price_pro', 'price_kudosly_pro_test');
});

describe('signature verification', function (): void {
    it('accepts a delivery with a valid signature', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        expect($user->fresh()->subscriptions()->count())->toBe(1);
    });

    it('rejects a delivery with a wrong signature', function (): void {
        $user = customer();

        deliver(
            StripeWebhook::subscriptionEvent('customer.subscription.created'),
            StripeWebhook::signature(
                StripeWebhook::subscriptionEvent('customer.subscription.created'),
                'whsec_the_wrong_secret',
            ),
        )->assertForbidden();

        expect($user->fresh()->subscriptions()->count())->toBe(0)
            ->and(StripeWebhookEvent::query()->count())->toBe(0);
    });

    it('rejects a delivery with no signature header', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'), '')
            ->assertForbidden();

        expect($user->fresh()->subscriptions()->count())->toBe(0);
    });

    it('rejects a delivery signed with a different payload', function (): void {
        $user = customer();

        $signed = StripeWebhook::subscriptionEvent('customer.subscription.created');

        // The body is altered after it was signed, so the signature no longer
        // matches what is delivered.
        $tampered = $signed;
        $tampered['data']['object']['status'] = 'past_due';

        deliver($tampered, StripeWebhook::signature($signed))
            ->assertForbidden();

        expect($user->fresh()->subscriptions()->count())->toBe(0);
    });

    it('rejects a delivery whose timestamp is outside the tolerance', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');

        deliver(
            $payload,
            StripeWebhook::signature($payload, null, time() - 3600),
        )->assertForbidden();

        expect($user->fresh()->subscriptions()->count())->toBe(0);
    });

    it('refuses every delivery when no webhook secret is configured', function (): void {
        config()->set('cashier.webhook.secret', null);

        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertStatus(503);

        expect($user->fresh()->subscriptions()->count())->toBe(0);
    });

    it('never grants Pro access from an unverified delivery', function (): void {
        $user = customer();

        deliver(
            StripeWebhook::subscriptionEvent('customer.subscription.created'),
            't=1,v1=deadbeef',
        )->assertForbidden();

        expect($user->fresh()->subscribedToPro())->toBeFalse()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free')
            ->and($user->fresh()->spaceQuota())->toBe(3);
    });
});

describe('subscription creation', function (): void {
    it('records the subscription and grants Pro access', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        $subscription = $user->fresh()->subscriptions()->sole();

        expect($subscription->stripe_id)->toBe('sub_test_123')
            ->and($subscription->stripe_status)->toBe('active')
            ->and($subscription->stripe_price)->toBe('price_kudosly_pro_test')
            ->and($subscription->type)->toBe('default');

        expect($user->fresh()->subscribedToPro())->toBeTrue()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('pro')
            ->and($user->fresh()->spaceQuota())->toBe(25)
            ->and($user->fresh()->testimonialQuota())->toBe(1000);
    });

    it('records the subscription item', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        $item = $user->fresh()->subscriptions()->sole()->items()->sole();

        expect($item->stripe_id)->toBe('si_test_123')
            ->and($item->stripe_price)->toBe('price_kudosly_pro_test')
            ->and($item->stripe_product)->toBe('prod_test_123')
            ->and($item->quantity)->toBe(1);
    });

    it('does not grant Pro access for a subscription to another price', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');
        $payload['data']['object']['items']['data'][0]['price']['id'] = 'price_some_other';

        deliver($payload)->assertOk();

        expect($user->fresh()->subscribedToPro())->toBeFalse()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free')
            ->and($user->fresh()->spaceQuota())->toBe(3);
    });

    it('does not grant Pro access for an inactive subscription', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');
        $payload['data']['object']['status'] = 'past_due';

        deliver($payload)->assertOk();

        expect($user->fresh()->subscribedToPro())->toBeFalse()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free');
    });

    it('ignores an event for an account that does not exist', function (): void {
        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        expect(User::query()->count())->toBe(0)
            ->and(StripeWebhookEvent::query()->count())->toBe(1);
    });

    it('ignores an event without a customer', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');
        unset($payload['data']['object']['customer']);

        deliver($payload)->assertOk();

        expect($user->fresh()->subscriptions()->count())->toBe(0);
    });
});

describe('idempotency', function (): void {
    it('applies a repeated delivery only once', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');

        deliver($payload)->assertOk();
        deliver($payload)->assertOk();
        deliver($payload)->assertOk();

        expect($user->fresh()->subscriptions()->count())->toBe(1)
            ->and($user->fresh()->subscriptions()->sole()->items()->count())->toBe(1)
            ->and(StripeWebhookEvent::query()->count())->toBe(1);
    });

    it('records the event once it has been processed', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');

        deliver($payload)->assertOk();

        $event = StripeWebhookEvent::query()->sole();

        expect($event->stripe_event_id)->toBe($payload['id'])
            ->and($event->type)->toBe('customer.subscription.created')
            ->and($event->user_id)->toBe($user->id)
            ->and($event->processed_at)->not->toBeNull();
    });

    it('applies a repeated update delivery only once', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.updated');
        $payload['data']['object']['status'] = 'past_due';

        deliver($payload)->assertOk();
        deliver($payload)->assertOk();

        expect($user->fresh()->subscriptions()->count())->toBe(1)
            ->and($user->fresh()->subscriptions()->sole()->stripe_status)->toBe('past_due');
    });
});

describe('subscription updates', function (): void {
    it('updates the status of an existing subscription', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        expect($user->fresh()->subscribedToPro())->toBeTrue();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.updated');
        $payload['data']['object']['status'] = 'past_due';

        deliver($payload)->assertOk();

        expect($user->fresh()->subscriptions()->sole()->stripe_status)->toBe('past_due')
            ->and($user->fresh()->subscribedToPro())->toBeFalse()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free');
    });

    it('keeps Pro access while the subscription is cancelled at period end', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.updated');
        $payload['data']['object']['cancel_at_period_end'] = true;
        $payload['data']['object']['current_period_end'] = now()->addDays(10)->getTimestamp();

        deliver($payload)->assertOk();

        $subscription = $user->fresh()->subscriptions()->sole();

        expect($subscription->stripe_status)->toBe('active')
            ->and($subscription->ends_at)->not->toBeNull()
            ->and($user->fresh()->onGracePeriod())->toBeTrue()
            ->and($user->fresh()->subscribedToPro())->toBeTrue();
    });

    it('removes a subscription that expired incomplete', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        expect($user->fresh()->subscriptions()->count())->toBe(1);

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.updated');
        $payload['data']['object']['status'] = 'incomplete_expired';

        deliver($payload)->assertOk();

        expect($user->fresh()->subscriptions()->count())->toBe(0)
            ->and($user->fresh()->subscribedToPro())->toBeFalse();
    });

    it('records a trial end when the payload carries one', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');
        $payload['data']['object']['status'] = 'trialing';
        $payload['data']['object']['trial_end'] = now()->addDays(14)->getTimestamp();

        deliver($payload)->assertOk();

        $subscription = $user->fresh()->subscriptions()->sole();

        expect($subscription->trial_ends_at)->not->toBeNull()
            ->and($subscription->stripe_status)->toBe('trialing')
            ->and($user->fresh()->subscribedToPro())->toBeTrue();
    });

    it('handles a payload without subscription items', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');
        unset($payload['data']['object']['items']);

        deliver($payload)->assertOk();

        $subscription = $user->fresh()->subscriptions()->sole();

        expect($subscription->stripe_id)->toBe('sub_test_123')
            ->and($subscription->stripe_price)->toBeNull()
            // Without a price the subscription cannot match the Pro price, so
            // Pro access is not granted.
            ->and($user->fresh()->subscribedToPro())->toBeFalse();
    });
});

describe('subscription deletion', function (): void {
    it('marks the subscription as canceled', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        expect($user->fresh()->subscribedToPro())->toBeTrue();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.deleted'))
            ->assertOk();

        $subscription = $user->fresh()->subscriptions()->sole();

        expect($subscription->stripe_status)->toBe('canceled')
            ->and($subscription->ends_at)->not->toBeNull();
    });

    it('removes Pro access once the cancelled subscription has ended', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.deleted');
        $payload['data']['object']['canceled_at'] = now()->subDay()->getTimestamp();

        deliver($payload)->assertOk();

        expect($user->fresh()->onGracePeriod())->toBeFalse()
            ->and($user->fresh()->subscribedToPro())->toBeFalse()
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free')
            ->and($user->fresh()->spaceQuota())->toBe(3);
    });

    it('ignores a deletion for a subscription that does not exist', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.deleted'))
            ->assertOk();

        expect($user->fresh()->subscriptions()->count())->toBe(0);
    });

    it('keeps the spaces and testimonials when the subscription is deleted', function (): void {
        $user = customer();

        $space = Space::factory()->create(['user_id' => $user->id]);

        Testimonial::factory()->create([
            'user_id' => $user->id,
            'space_id' => $space->id,
        ]);

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.deleted');
        $payload['data']['object']['canceled_at'] = now()->subDay()->getTimestamp();

        deliver($payload)->assertOk();

        expect($user->fresh()->spaces()->count())->toBe(1)
            ->and($user->fresh()->testimonials()->count())->toBe(1)
            ->and($user->fresh()->subscribedToPro())->toBeFalse();
    });
});

describe('plan synchronisation', function (): void {
    it('moves the account onto the Pro plan when a Pro subscription is created', function (): void {
        $user = customer();

        expect($user->plan->slug)->toBe('free');

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        expect($user->fresh()->plan->slug)->toBe('pro')
            ->and($user->fresh()->plan_id)->toBe(Plan::pro()->getKey())
            ->and($user->fresh()->effectivePlan()->slug)->toBe('pro');
    });

    it('moves the account back onto the Free plan when the subscription ends', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        expect($user->fresh()->plan->slug)->toBe('pro');

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.deleted');
        $payload['data']['object']['canceled_at'] = now()->subDay()->getTimestamp();

        deliver($payload)->assertOk();

        expect($user->fresh()->plan->slug)->toBe('free')
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free')
            ->and($user->fresh()->subscribedToPro())->toBeFalse();
    });

    it('keeps the account on the Pro plan while a cancelled subscription is still running', function (): void {
        $user = customer();

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.updated');
        $payload['data']['object']['cancel_at_period_end'] = true;
        $payload['data']['object']['current_period_end'] = now()->addDays(10)->getTimestamp();

        deliver($payload)->assertOk();

        // The period the customer paid for has not ended, so Pro access and
        // the Pro plan are both kept.
        expect($user->fresh()->plan->slug)->toBe('pro')
            ->and($user->fresh()->onGracePeriod())->toBeTrue();
    });

    it('keeps the account on the Free plan for a subscription to another price', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');
        $payload['data']['object']['items']['data'][0]['price']['id'] = 'price_some_other';

        deliver($payload)->assertOk();

        expect($user->fresh()->plan->slug)->toBe('free')
            ->and($user->fresh()->effectivePlan()->slug)->toBe('free');
    });

    it('does not move any account when the delivery names an unknown customer', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');
        $payload['data']['object']['customer'] = 'cus_does_not_exist';

        deliver($payload)->assertOk();

        expect($user->fresh()->plan->slug)->toBe('free')
            ->and($user->fresh()->subscriptions()->count())->toBe(0);
    });

    it('applies the plan change only once for a repeated delivery', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');

        deliver($payload)->assertOk();
        deliver($payload)->assertOk();
        deliver($payload)->assertOk();

        expect($user->fresh()->plan->slug)->toBe('pro')
            ->and($user->fresh()->subscriptions()->count())->toBe(1)
            ->and(StripeWebhookEvent::query()->count())->toBe(1);
    });

    it('keeps the spaces and testimonials of a downgraded account', function (): void {
        $user = customer();

        $space = Space::factory()->create(['user_id' => $user->id]);

        Testimonial::factory()->create([
            'user_id' => $user->id,
            'space_id' => $space->id,
        ]);

        deliver(StripeWebhook::subscriptionEvent('customer.subscription.created'))
            ->assertOk();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.deleted');
        $payload['data']['object']['canceled_at'] = now()->subDay()->getTimestamp();

        deliver($payload)->assertOk();

        // Nothing is deleted when an account is downgraded.
        expect($user->fresh()->plan->slug)->toBe('free')
            ->and($user->fresh()->spaces()->count())->toBe(1)
            ->and($user->fresh()->testimonials()->count())->toBe(1);
    });
});

describe('other events', function (): void {
    it('acknowledges an event it does not act on', function (): void {
        $user = customer();

        $payload = StripeWebhook::subscriptionEvent('customer.subscription.created');
        $payload['type'] = 'invoice.payment_succeeded';
        $payload['id'] = 'evt_test_invoice';

        deliver($payload)->assertOk();

        expect($user->fresh()->subscriptions()->count())->toBe(0)
            ->and(StripeWebhookEvent::query()->count())->toBe(0);
    });

    it('acknowledges a payload that is not valid json', function (): void {
        $body = 'not json at all';

        $this->call(
            'POST',
            route('cashier.webhook'),
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1='.hash_hmac('sha256', time().'.'.$body, StripeWebhook::SECRET),
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        )->assertOk();
    });
});
