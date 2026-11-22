<?php

use App\Models\User;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Customer as StripeCustomer;
use Tests\Support\FakeStripeClient;

/**
 * A focused proof that the fake Stripe client intercepts every outbound call
 * Cashier makes, and that nothing reaches the network.
 */
beforeEach(function (): void {
    FakeStripeClient::bind();
});

it('intercepts customer creation and returns a realistic customer', function (): void {
    $user = User::factory()->create();

    $customer = $user->createAsStripeCustomer(['email' => 'owner@example.com']);

    expect($customer)->toBeInstanceOf(StripeCustomer::class)
        ->and($customer->id)->toStartWith('cus_fake_')
        ->and($customer->email)->toBe('owner@example.com');

    expect(FakeStripeClient::callsTo('customers', 'create'))->toHaveCount(1)
        ->and(FakeStripeClient::paramsFor('customers', 'create')['email'])->toBe('owner@example.com');
});

it('intercepts customer retrieval for a returning customer', function (): void {
    $user = User::factory()->create();

    $user->createAsStripeCustomer(['email' => 'owner@example.com']);

    $retrieved = $user->asStripeCustomer();

    expect($retrieved)->toBeInstanceOf(StripeCustomer::class)
        ->and(FakeStripeClient::callsTo('customers', 'retrieve'))->toHaveCount(1)
        ->and(FakeStripeClient::paramsFor('customers', 'retrieve')['id'])->toBe('cus_fake_1');
});

it('intercepts checkout session creation and returns a realistic session', function (): void {
    $user = User::factory()->create();

    $checkout = $user->newSubscription('default', 'price_kudosly_pro_test')->checkout([
        'success_url' => route('billing'),
        'cancel_url' => route('billing'),
    ]);

    $session = $checkout->asStripeCheckoutSession();

    expect($session)->toBeInstanceOf(StripeCheckoutSession::class)
        ->and($session->id)->toStartWith('cs_fake_')
        ->and($session->url)->toBeString()
        ->and($session->mode)->toBe('subscription')
        ->and($session->status)->toBe('open')
        ->and($session->customer)->toBe('cus_fake_1');

    expect(FakeStripeClient::callsTo('checkout.sessions', 'create'))->toHaveCount(1);

    $params = FakeStripeClient::paramsFor('checkout.sessions', 'create');

    expect($params['mode'])->toBe('subscription')
        ->and($params['line_items'])->toBe([['price' => 'price_kudosly_pro_test', 'quantity' => 1]])
        ->and($params['customer'])->toBe('cus_fake_1')
        ->and($params['success_url'])->toBe(route('billing'))
        ->and($params['cancel_url'])->toBe(route('billing'));
});

it('records every request made through the fake', function (): void {
    $user = User::factory()->create();

    $user->newSubscription('default', 'price_kudosly_pro_test')->checkout([
        'success_url' => route('billing'),
        'cancel_url' => route('billing'),
    ]);

    expect(FakeStripeClient::$calls)->toHaveCount(2)
        ->and(FakeStripeClient::$calls[0]['service'])->toBe('customers')
        ->and(FakeStripeClient::$calls[0]['method'])->toBe('create')
        ->and(FakeStripeClient::$calls[1]['service'])->toBe('checkout.sessions')
        ->and(FakeStripeClient::$calls[1]['method'])->toBe('create');
});
