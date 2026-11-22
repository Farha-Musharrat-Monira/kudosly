<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Subscription as CashierSubscription;
use Stripe\Exception\ExceptionInterface as StripeException;

/**
 * Cancels the account's Pro subscription at the end of the paid period.
 *
 * Cancelling stops the next renewal; it does not take the account's Pro
 * access away immediately, because the period it was paid for has not ended
 * yet. The account stays on Pro until that period is over, and the Stripe
 * webhook that ends the subscription is what downgrades it to Free.
 *
 * The subscription that gets cancelled is the authenticated account's own
 * subscription, read from the database. No subscription identifier is taken
 * from the request, so one account can never cancel another's subscription.
 */
class BillingCancelController extends Controller
{
    /**
     * Cancel the authenticated account's subscription at period end.
     */
    public function __invoke(): RedirectResponse
    {
        $user = auth()->user();

        $subscription = $user->proSubscription();

        if ($subscription === null) {
            return back()->withErrors([
                'billing' => 'There is no subscription to cancel.',
            ]);
        }

        // A subscription that is already ending, or that has already ended, has
        // nothing left to cancel.
        if ($subscription->ends_at !== null) {
            return back()->withErrors([
                'billing' => 'This subscription is already cancelled.',
            ]);
        }

        try {
            $endsAt = $this->cancelAtPeriodEnd($user, $subscription);
        } catch (StripeException) {
            // Stripe refused the request, was unreachable, or rejected the
            // credentials. The subscription keeps running and the account
            // keeps its Pro access, because nothing was actually cancelled.
            return back()->withErrors([
                'billing' => 'We could not cancel your subscription. Please try again.',
            ]);
        }

        return back()->with('billing_status', 'cancelled')->with('billing_ends_at', $endsAt);
    }

    /**
     * Ask Stripe to stop the subscription renewing at the end of the period.
     *
     * Stripe answers with the updated subscription, which carries the end of
     * the current period. Reading the date from that response keeps this to a
     * single request to Stripe.
     */
    protected function cancelAtPeriodEnd(User $user, CashierSubscription $subscription): ?Carbon
    {
        $stripeSubscription = $user->stripe()->subscriptions->update(
            $subscription->stripe_id,
            ['cancel_at_period_end' => true],
        );

        $endsAt = $this->periodEndFrom($stripeSubscription);

        $subscription->forceFill([
            'stripe_status' => $stripeSubscription->status ?? $subscription->stripe_status,
            'ends_at' => $endsAt,
        ])->save();

        return $endsAt;
    }

    /**
     * The end of the current billing period, from the Stripe response.
     */
    protected function periodEndFrom(mixed $stripeSubscription): ?Carbon
    {
        $periodEnd = $stripeSubscription->current_period_end ?? null;

        if (! is_numeric($periodEnd)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $periodEnd);
    }
}
