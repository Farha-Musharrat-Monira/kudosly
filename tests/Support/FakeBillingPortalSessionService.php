<?php

namespace Tests\Support;

use Stripe\BillingPortal\Session as StripeBillingPortalSession;

/**
 * The subset of the Stripe Billing Portal Session service that Cashier uses.
 *
 * `create()` records the request and returns a realistic
 * `Stripe\BillingPortal\Session` carrying an id and a url.
 */
class FakeBillingPortalSessionService
{
    public function __construct(
        protected FakeStripeClient $client,
    ) {
        //
    }

    /**
     * Create a Billing Portal session.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function create($params = null, $opts = null): StripeBillingPortalSession
    {
        $params = $params ?? [];

        $this->client->record('billingPortal.sessions', 'create', $params);

        FakeStripeClient::throwIfFailing();

        $session = StripeBillingPortalSession::constructFrom([
            'id' => 'bps_fake_'.count($this->client->callsTo('billingPortal.sessions', 'create')),
            'object' => 'billing_portal.session',
            'url' => 'https://billing.stripe.com/session/bps_fake_session',
            'customer' => $params['customer'] ?? null,
            'return_url' => $params['return_url'] ?? null,
            'livemode' => false,
        ]);

        FakeStripeClient::$billingPortalSession = $session;

        return $session;
    }
}
