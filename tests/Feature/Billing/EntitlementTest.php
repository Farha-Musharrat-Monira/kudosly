<?php

use App\Models\Plan;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\PlanCatalog;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;

/**
 * Attach a subscription to a user without touching Stripe.
 *
 * Cashier's own factories are used so the records match the installed
 * Cashier version, and no request is ever made to Stripe.
 */
function subscribe(User $user, string $status, array $overrides = []): Subscription
{
    $price = $overrides['stripe_price'] ?? config('services.stripe.price_pro');

    $subscription = Subscription::factory()
        ->create([
            'user_id' => $user->id,
            'stripe_status' => $status,
            'stripe_price' => $price,
        ]);

    if (array_key_exists('ends_at', $overrides)) {
        $subscription->ends_at = $overrides['ends_at'];
        $subscription->save();
    }

    // The item records the price that is actually subscribed to. When no
    // price is configured the subscription cannot match any price, so the
    // item carries a placeholder that never matches the configured one.
    SubscriptionItem::factory()->create([
        'subscription_id' => $subscription->id,
        'stripe_price' => is_string($price) && $price !== '' ? $price : 'price_unconfigured',
    ]);

    return $subscription;
}

it('starts every new account on the free plan', function () {
    $user = verifiedUser();

    expect($user->onFreePlan())->toBeTrue()
        ->and($user->subscribedToPro())->toBeFalse()
        ->and($user->spaceQuota())->toBe(3)
        ->and($user->testimonialQuota())->toBe(100);
});

it('keeps the free and pro limits unchanged', function () {
    expect(PlanCatalog::maxSpaces('free'))->toBe(3)
        ->and(PlanCatalog::maxTestimonialsPerSpace('free'))->toBe(100)
        ->and(PlanCatalog::maxSpaces('pro'))->toBe(25)
        ->and(PlanCatalog::maxTestimonialsPerSpace('pro'))->toBe(1000)
        ->and(PlanCatalog::priceCents('pro'))->toBe(99900)
        ->and(PlanCatalog::priceLabel('pro'))->toBe('$999.00/mo');
});

it('grants pro access to an active subscription', function () {
    $user = verifiedUser();
    subscribe($user, 'active');

    expect($user->subscribedToPro())->toBeTrue()
        ->and($user->onFreePlan())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('pro')
        ->and($user->spaceQuota())->toBe(25)
        ->and($user->testimonialQuota())->toBe(1000);
});

it('grants pro access to a trialing subscription', function () {
    $user = verifiedUser();
    subscribe($user, 'trialing');

    expect($user->subscribedToPro())->toBeTrue()
        ->and($user->effectivePlan()->slug)->toBe('pro');
});

it('keeps pro access during the grace period after cancelling at period end', function () {
    $user = verifiedUser();
    subscribe($user, 'active', ['ends_at' => now()->addDays(10)]);

    expect($user->onGracePeriod())->toBeTrue()
        ->and($user->subscribedToPro())->toBeTrue()
        ->and($user->effectivePlan()->slug)->toBe('pro');
});

it('drops pro access once the grace period has ended', function () {
    $user = verifiedUser();
    subscribe($user, 'canceled', ['ends_at' => now()->subDay()]);

    expect($user->onGracePeriod())->toBeFalse()
        ->and($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free')
        ->and($user->spaceQuota())->toBe(3);
});

it('does not grant pro access for a past due subscription', function () {
    $user = verifiedUser();
    subscribe($user, 'past_due');

    expect($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free')
        ->and($user->spaceQuota())->toBe(3)
        ->and($user->testimonialQuota())->toBe(100);
});

it('does not grant pro access for an unpaid subscription', function () {
    $user = verifiedUser();
    subscribe($user, 'unpaid');

    expect($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free');
});

it('does not grant pro access for an incomplete subscription', function () {
    $user = verifiedUser();
    subscribe($user, 'incomplete');

    expect($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free');
});

it('does not grant pro access for an incomplete expired subscription', function () {
    $user = verifiedUser();
    subscribe($user, 'incomplete_expired');

    expect($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free');
});

it('does not grant pro access for a subscription to another price', function () {
    $user = verifiedUser();
    subscribe($user, 'active', ['stripe_price' => 'price_some_other']);

    expect($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free');
});

it('does not grant pro access when no pro price is configured', function () {
    config()->set('services.stripe.price_pro', null);

    $user = verifiedUser();
    subscribe($user, 'active');

    expect($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free');
});

it('does not grant pro access when the pro price is an empty string', function () {
    config()->set('services.stripe.price_pro', '');

    $user = verifiedUser();
    subscribe($user, 'active');

    expect($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free');
});

it('does not grant pro access when the configured price does not match the subscription', function () {
    config()->set('services.stripe.price_pro', 'price_configured');

    $user = verifiedUser();
    subscribe($user, 'active', ['stripe_price' => 'price_different']);

    expect($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free')
        ->and($user->spaceQuota())->toBe(3);
});

it('creates no subscription when the pro factory runs without a configured price', function () {
    config()->set('services.stripe.price_pro', null);

    $user = User::factory()->proPlan()->create();

    expect($user->subscriptions()->count())->toBe(0)
        ->and($user->subscribedToPro())->toBeFalse()
        ->and($user->spaceQuota())->toBe(3);
});

it('does not grant pro access when the account has no subscription', function () {
    $user = verifiedUser();

    expect($user->proSubscription())->toBeNull()
        ->and($user->subscribedToPro())->toBeFalse();
});

it('never grants pro access from a stored pro plan id alone', function () {
    $user = verifiedUser();
    $user->forceFill(['plan_id' => Plan::pro()->getKey()])->save();

    expect($user->plan->slug)->toBe('pro')
        ->and($user->subscribedToPro())->toBeFalse()
        ->and($user->effectivePlan()->slug)->toBe('free')
        ->and($user->spaceQuota())->toBe(3);
});

it('keeps pro access when the stored plan id is free but the subscription is active', function () {
    $user = verifiedUser();
    subscribe($user, 'active');

    expect($user->plan->slug)->toBe('free')
        ->and($user->effectivePlan()->slug)->toBe('pro');
});

it('preserves spaces and testimonials when access is downgraded', function () {
    $user = verifiedUser();
    $subscription = subscribe($user, 'active');

    $space = Space::factory()->create(['user_id' => $user->id]);
    Testimonial::factory()->create(['user_id' => $user->id, 'space_id' => $space->id]);

    expect($user->spaceQuota())->toBe(25);

    // The subscription lapses.
    $subscription->forceFill(['stripe_status' => 'past_due'])->save();

    expect($user->subscribedToPro())->toBeFalse()
        ->and($user->spaces()->count())->toBe(1)
        ->and($user->testimonials()->count())->toBe(1)
        ->and($user->spacesUsed())->toBe(1);
});

it('blocks new spaces once access is downgraded', function () {
    $user = verifiedUser();
    $subscription = subscribe($user, 'active');

    Space::factory()->count(4)->create(['user_id' => $user->id]);

    expect($user->canCreateSpace())->toBeTrue();

    $subscription->forceFill(['stripe_status' => 'past_due'])->save();

    expect($user->canCreateSpace())->toBeFalse()
        ->and($user->remainingSpaces())->toBe(0);
});

it('falls back to the account email for the billing email', function () {
    $user = verifiedUser(['email' => 'owner@example.com']);

    expect($user->billing_email)->toBeNull()
        ->and($user->billingEmail())->toBe('owner@example.com');
});

it('uses a dedicated billing email when one is set', function () {
    $user = verifiedUser(['email' => 'owner@example.com']);
    $user->forceFill(['billing_email' => 'billing@example.com'])->save();

    expect($user->billingEmail())->toBe('billing@example.com');
});

it('does not allow the billing email to be mass assigned', function () {
    $user = verifiedUser();

    $user->update(['billing_email' => 'injected@example.com']);

    expect($user->fresh()->billing_email)->toBeNull();
});

it('exposes the resolved pro price id from configuration', function () {
    $user = verifiedUser();

    expect($user->proPriceId())->toBe(config('services.stripe.price_pro'));

    config()->set('services.stripe.price_pro', '');

    expect($user->proPriceId())->toBeNull();
});
