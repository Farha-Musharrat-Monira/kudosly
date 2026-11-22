<?php

namespace Tests\Support;

use Stripe\Customer as StripeCustomer;

/**
 * The subset of the Stripe Customer service that Cashier uses.
 *
 * `create()` records the request and returns a realistic `Stripe\Customer`;
 * `retrieve()` returns the customer that was created, so a returning customer
 * never reaches the network.
 */
class FakeCustomerService
{
    public function __construct(
        protected FakeStripeClient $client,
    ) {
        //
    }

    /**
     * Create a Stripe customer.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function create($params = null, $opts = null): StripeCustomer
    {
        $params = $params ?? [];

        $this->client->record('customers', 'create', $params);

        FakeStripeClient::throwIfFailing();

        $customer = StripeCustomer::constructFrom(array_merge([
            'id' => 'cus_fake_'.count($this->client->callsTo('customers', 'create')),
            'object' => 'customer',
            'email' => $params['email'] ?? null,
            'name' => $params['name'] ?? null,
            'livemode' => false,
        ], array_filter([
            'email' => $params['email'] ?? null,
            'name' => $params['name'] ?? null,
        ], fn ($value): bool => $value !== null)));

        FakeStripeClient::$customer = $customer;

        return $customer;
    }

    /**
     * Retrieve a Stripe customer.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function retrieve($id, $params = null, $opts = null): StripeCustomer
    {
        $params = $params ?? [];

        $this->client->record('customers', 'retrieve', array_merge(['id' => $id], $params));

        if (FakeStripeClient::$customer !== null) {
            return FakeStripeClient::$customer;
        }

        $customer = StripeCustomer::constructFrom([
            'id' => $id,
            'object' => 'customer',
            'livemode' => false,
        ]);

        FakeStripeClient::$customer = $customer;

        return $customer;
    }

    /**
     * Update a Stripe customer.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function update($id, $params = null, $opts = null): StripeCustomer
    {
        $params = $params ?? [];

        $this->client->record('customers', 'update', array_merge(['id' => $id], $params));

        return FakeStripeClient::$customer ?? StripeCustomer::constructFrom([
            'id' => $id,
            'object' => 'customer',
        ]);
    }
}
