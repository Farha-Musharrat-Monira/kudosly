<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CheckoutAvailability;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;
use Stripe\Exception\ExceptionInterface as StripeException;

/**
 * Starts a Stripe Checkout session for the Pro plan.
 *
 * Everything the session is built from comes from the server: the Price ID is
 * read from configuration, the customer is the authenticated account's own
 * Stripe customer, and the redirect URLs are generated here. Nothing the
 * browser submits is trusted, and no price, customer or subscription metadata
 * is accepted from the request.
 *
 * Starting a checkout grants nothing. Pro access is decided by the live
 * subscription, so an account only becomes Pro once Stripe confirms the
 * subscription. That confirmation arrives through a webhook, which is a later
 * phase.
 */
class BillingCheckoutController extends Controller
{
    /**
     * Create a Checkout Session for the Pro plan and redirect to Stripe.
     */
    public function __invoke(): RedirectResponse
    {
        $user = auth()->user();

        $price = $user->proPriceId();

        if ($price === null || ! CheckoutAvailability::available()) {
            return back()->withErrors([
                'billing' => 'Checkout is not available right now. Please try again later.',
            ]);
        }

        // An account that already holds a live Pro subscription has nothing to
        // buy. The check reads the stored subscription rather than the plan
        // column, so an account whose subscription has lapsed can subscribe
        // again while an active subscriber is turned away.
        if ($user->subscribedToPro()) {
            return back()->withErrors([
                'billing' => 'Your account is already on the Pro plan.',
            ]);
        }

        try {
            $checkout = $this->newProCheckout($user, $price);
        } catch (StripeException) {
            // Stripe refused the request, was unreachable, or rejected the
            // credentials. The account is left exactly as it was: no
            // subscription, no plan change and no entitlement.
            return back()->withErrors([
                'billing' => 'We could not start the checkout. Please try again.',
            ]);
        }

        return $checkout->redirect();
    }

    /**
     * Build the Checkout Session for the Pro plan.
     *
     * The subscription type and price are fixed by the server. The customer is
     * the account's own, so a returning customer keeps the Stripe customer
     * that is already stored against the account.
     */
    protected function newProCheckout(User $user, string $price): Checkout
    {
        return $user->newSubscription('default', $price)->checkout([
            'success_url' => route('billing', ['checkout' => 'success']),
            'cancel_url' => route('billing', ['checkout' => 'cancelled']),
        ]);
    }
}
