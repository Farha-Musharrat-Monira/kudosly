<?php

namespace Tests\Support;

/**
 * The Stripe Billing Portal service.
 *
 * The portal is Stripe's own hosted page for managing a subscription. The
 * fake creates a session and returns its url, so the redirect can be asserted
 * without a request ever leaving the process.
 */
class FakeBillingPortalService
{
    /**
     * The Billing Portal session service.
     */
    public FakeBillingPortalSessionService $sessions;

    public function __construct(
        protected FakeStripeClient $client,
    ) {
        $this->sessions = new FakeBillingPortalSessionService($client);
    }
}
