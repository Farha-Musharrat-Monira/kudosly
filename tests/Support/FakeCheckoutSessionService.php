<?php

namespace Tests\Support;

use Stripe\Checkout\Session as StripeCheckoutSession;

/**
 * The subset of the Stripe Checkout Session service that Cashier uses.
 *
 * `create()` records the request and returns a realistic
 * `Stripe\Checkout\Session` carrying an id, a url, the customer, the mode and
 * the status.
 */
class FakeCheckoutSessionService
{
    public function __construct(
        protected FakeStripeClient $client,
    ) {
        //
    }

    /**
     * Create a Checkout Session.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function create($params = null, $opts = null): StripeCheckoutSession
    {
        $params = $params ?? [];

        $this->client->record('checkout.sessions', 'create', $params);

        FakeStripeClient::throwIfFailing();

        $session = StripeCheckoutSession::constructFrom([
            'id' => 'cs_fake_'.count($this->client->callsTo('checkout.sessions', 'create')),
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_fake_session',
            'customer' => $params['customer'] ?? null,
            'mode' => $params['mode'] ?? StripeCheckoutSession::MODE_PAYMENT,
            'status' => StripeCheckoutSession::STATUS_OPEN,
            'livemode' => false,
            'success_url' => $params['success_url'] ?? null,
            'cancel_url' => $params['cancel_url'] ?? null,
        ]);

        FakeStripeClient::$checkoutSession = $session;

        return $session;
    }

    /**
     * Retrieve a Checkout Session.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function retrieve($id, $params = null, $opts = null): StripeCheckoutSession
    {
        $params = $params ?? [];

        $this->client->record('checkout.sessions', 'retrieve', array_merge(['id' => $id], $params));

        return FakeStripeClient::$checkoutSession ?? StripeCheckoutSession::constructFrom([
            'id' => $id,
            'object' => 'checkout.session',
            'status' => StripeCheckoutSession::STATUS_OPEN,
        ]);
    }
}
