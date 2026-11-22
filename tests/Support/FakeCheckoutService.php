<?php

namespace Tests\Support;

/**
 * The Stripe Checkout service.
 *
 * The `sessions` service is wired up directly in the constructor, so
 * `$stripe->checkout->sessions->create($params)` works exactly as it does
 * against the real Stripe SDK.
 */
class FakeCheckoutService
{
    /**
     * The Checkout Session service.
     */
    public FakeCheckoutSessionService $sessions;

    public function __construct(
        protected FakeStripeClient $client,
    ) {
        $this->sessions = new FakeCheckoutSessionService($client);
    }
}
