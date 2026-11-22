<?php

namespace App\Support;

/**
 * Decides whether a Stripe Checkout session can be started right now.
 *
 * The billing page and the checkout controller both ask this class, so the
 * button is disabled exactly when the controller would refuse the request. The
 * Stripe secret is only ever checked for presence: it is never returned,
 * echoed or logged.
 */
final class CheckoutAvailability
{
    /**
     * Whether the server holds a Stripe secret.
     */
    public static function hasStripeSecret(): bool
    {
        $secret = config('cashier.secret');

        return is_string($secret) && $secret !== '';
    }

    /**
     * Whether the server holds the Pro Price ID.
     */
    public static function hasProPrice(): bool
    {
        $price = config('services.stripe.price_pro');

        return is_string($price) && $price !== '';
    }

    /**
     * Whether checkout can be started.
     */
    public static function available(): bool
    {
        return self::hasStripeSecret() && self::hasProPrice();
    }

    /**
     * The reason checkout is unavailable, or null when it is available.
     */
    public static function unavailableReason(): ?string
    {
        if (! self::hasProPrice()) {
            return 'The Pro plan price is not configured yet.';
        }

        if (! self::hasStripeSecret()) {
            return 'Billing is not configured on this server yet.';
        }

        return null;
    }
}
