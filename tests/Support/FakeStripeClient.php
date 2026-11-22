<?php

namespace Tests\Support;

use Stripe\BillingPortal\Session as StripeBillingPortalSession;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Customer as StripeCustomer;
use Stripe\StripeClient;

/**
 * An in-memory stand-in for the Stripe API client.
 *
 * Cashier resolves its client through the container (`Cashier::stripe()` calls
 * `app(StripeClient::class, ['config' => $config])`), so binding this class in
 * place of `Stripe\StripeClient` intercepts every outbound Stripe call. No
 * request ever leaves the process, so the suite never touches the network.
 *
 * The fake mirrors the parts of the Stripe SDK that Cashier actually uses:
 * customer creation and retrieval, and Checkout Session creation. Every
 * request is recorded in `FakeStripeClient::$calls` so tests can assert on the
 * exact payloads that were sent.
 */
class FakeStripeClient extends StripeClient
{
    /**
     * Every request made through the fake, in order.
     *
     * Each entry is `['service' => string, 'method' => string, 'params' => array]`.
     *
     * @var list<array{service: string, method: string, params: array}>
     */
    public static array $calls = [];

    /**
     * The customer returned when a customer is created.
     */
    public static ?StripeCustomer $customer = null;

    /**
     * The Checkout Session returned when a session is created.
     */
    public static ?StripeCheckoutSession $checkoutSession = null;

    /**
     * The Billing Portal Session returned when a session is created.
     */
    public static ?StripeBillingPortalSession $billingPortalSession = null;

    /**
     * An exception thrown by the fake instead of performing the request.
     */
    public static ?\Throwable $failure = null;

    /**
     * Reset the recorded state between tests.
     */
    public static function reset(): void
    {
        static::$calls = [];
        static::$customer = null;
        static::$checkoutSession = null;
        static::$billingPortalSession = null;
        static::$failure = null;

        FakeSubscriptionService::$subscription = null;
    }

    /**
     * Make the fake throw instead of performing a request.
     */
    public static function failWith(\Throwable $exception): void
    {
        static::$failure = $exception;
    }

    /**
     * Bind the fake in place of the real Stripe client.
     *
     * Cashier resolves its client through the container with an explicit
     * `['config' => ...]` parameter, which makes Laravel bypass a shared
     * `instance()` binding and rebuild the class instead. A closure binding is
     * used here because the container invokes it whatever parameters it is
     * given, so every Stripe call lands on the fake.
     */
    public static function bind(): void
    {
        static::reset();

        app()->bind(StripeClient::class, fn (): static => new static([
            'api_key' => 'sk_test_fake',
            'stripe_version' => '2025-01-01',
        ]));
    }

    /**
     * The requests made to a given service, optionally narrowed to one method.
     *
     * @return list<array{service: string, method: string, params: array}>
     */
    public static function callsTo(string $service, ?string $method = null): array
    {
        return array_values(array_filter(
            static::$calls,
            fn (array $call): bool => $call['service'] === $service
                && ($method === null || $call['method'] === $method),
        ));
    }

    /**
     * The parameters of the single request made to a service and method.
     *
     * @return array<string, mixed>
     */
    public static function paramsFor(string $service, string $method): array
    {
        $calls = static::callsTo($service, $method);

        return $calls === [] ? [] : $calls[0]['params'];
    }

    /**
     * Record a request made through the fake.
     *
     * @param  array<string, mixed>  $params
     */
    public static function record(string $service, string $method, array $params): void
    {
        static::$calls[] = [
            'service' => $service,
            'method' => $method,
            'params' => $params,
        ];
    }

    /**
     * Throw the configured failure, if one was set.
     */
    public static function throwIfFailing(): void
    {
        if (static::$failure !== null) {
            throw static::$failure;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function __get($name)
    {
        return match ($name) {
            'customers' => new FakeCustomerService($this),
            'checkout' => new FakeCheckoutService($this),
            'billingPortal' => new FakeBillingPortalService($this),
            'subscriptions' => new FakeSubscriptionService($this),
            default => parent::__get($name),
        };
    }
}
