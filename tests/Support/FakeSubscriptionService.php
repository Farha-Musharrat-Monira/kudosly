<?php

namespace Tests\Support;

use Stripe\Subscription as StripeSubscription;

/**
 * The subset of the Stripe Subscription service that Cashier uses.
 *
 * `update()` records the request and returns an updated subscription carrying
 * the options that were sent, so a cancellation can be asserted without a
 * request ever leaving the process.
 */
class FakeSubscriptionService
{
    /**
     * The subscription returned by the fake.
     */
    public static ?StripeSubscription $subscription = null;

    public function __construct(
        protected FakeStripeClient $client,
    ) {
        //
    }

    /**
     * Update a subscription.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function update($id, $params = null, $opts = null): StripeSubscription
    {
        $params = $params ?? [];

        $this->client->record('subscriptions', 'update', array_merge(['id' => $id], $params));

        FakeStripeClient::throwIfFailing();

        $subscription = StripeSubscription::constructFrom(array_merge([
            'id' => $id,
            'object' => 'subscription',
            'status' => StripeSubscription::STATUS_ACTIVE,
            'current_period_end' => now()->addMonth()->getTimestamp(),
            'livemode' => false,
        ], static::$subscription ? static::$subscription->toArray() : [], array_filter(
            $params,
            fn (string $key): bool => $key !== 'id',
        )));

        static::$subscription = $subscription;

        return $subscription;
    }

    /**
     * Retrieve a subscription.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function retrieve($id, $params = null, $opts = null): StripeSubscription
    {
        $params = $params ?? [];

        $this->client->record('subscriptions', 'retrieve', array_merge(['id' => $id], $params));

        if (static::$subscription !== null) {
            return static::$subscription;
        }

        $subscription = StripeSubscription::constructFrom([
            'id' => $id,
            'object' => 'subscription',
            'status' => StripeSubscription::STATUS_ACTIVE,
            'current_period_end' => now()->addMonth()->getTimestamp(),
            'livemode' => false,
        ]);

        static::$subscription = $subscription;

        return $subscription;
    }

    /**
     * Cancel a subscription immediately.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function cancel($id, $params = null, $opts = null): StripeSubscription
    {
        $params = $params ?? [];

        $this->client->record('subscriptions', 'cancel', array_merge(['id' => $id], $params));

        FakeStripeClient::throwIfFailing();

        $subscription = StripeSubscription::constructFrom([
            'id' => $id,
            'object' => 'subscription',
            'status' => StripeSubscription::STATUS_CANCELED,
            'canceled_at' => now()->getTimestamp(),
            'livemode' => false,
        ]);

        static::$subscription = $subscription;

        return $subscription;
    }
}
