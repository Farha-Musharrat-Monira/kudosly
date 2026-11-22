<?php

namespace Tests\Support;

/**
 * Builds Stripe webhook deliveries for the tests.
 *
 * A Stripe signature header carries the timestamp the delivery was signed at
 * and one or more HMAC signatures over "timestamp.payload". This helper signs
 * a payload the same way the Stripe SDK does, so a delivery produced here is
 * accepted by the SDK's own verification.
 */
final class StripeWebhook
{
    /**
     * The secret the signed deliveries are produced with.
     */
    public const SECRET = 'whsec_test_kudosly';

    /**
     * Sign a payload and return the header Stripe would send.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function signature(array $payload, ?string $secret = null, ?int $timestamp = null): string
    {
        $secret ??= self::SECRET;

        $timestamp ??= time();

        $body = self::body($payload);

        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * The raw request body for a payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function body(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * A signed delivery of a subscription event.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function subscriptionEvent(string $type, array $overrides = []): array
    {
        $subscription = array_merge([
            'id' => 'sub_test_123',
            'object' => 'subscription',
            'customer' => 'cus_test_123',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'current_period_end' => now()->addMonth()->getTimestamp(),
            'metadata' => [
                'name' => 'default',
                'type' => 'default',
            ],
            'items' => [
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'si_test_123',
                        'object' => 'subscription_item',
                        'quantity' => 1,
                        'price' => [
                            'id' => 'price_kudosly_pro_test',
                            'object' => 'price',
                            'product' => 'prod_test_123',
                        ],
                    ],
                ],
            ],
        ], $overrides);

        // A deletion event always carries the date the subscription was
        // canceled on. The other events describe a subscription that is
        // running, so they carry no cancellation date at all.
        if ($type === 'customer.subscription.deleted') {
            $subscription['canceled_at'] ??= now()->getTimestamp();
        }

        return array_merge([
            'id' => 'evt_test_'.substr(md5($type.$subscription['id']), 0, 12),
            'object' => 'event',
            'api_version' => '2025-01-01',
            'created' => time(),
            'livemode' => false,
            'pending_webhooks' => 1,
            'type' => $type,
            'data' => [
                'object' => $subscription,
            ],
        ], $overrides);
    }
}
