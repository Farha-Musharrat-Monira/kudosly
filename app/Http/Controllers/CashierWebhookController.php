<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ProcessedWebhookEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Cashier\Http\Controllers\WebhookController as BaseWebhookController;
use Stripe\Subscription as StripeSubscription;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Stripe webhook endpoint for Kudosly.
 *
 * Cashier verifies the signature of every delivery and dispatches the event to
 * the matching handler. This controller extends that behaviour and replaces
 * the three subscription handlers with ones that work entirely from the
 * payload Stripe sent, for three reasons:
 *
 *  - Cashier's handlers read the current period end from the Stripe API, which
 *    would make every webhook delivery depend on a second network call.
 *  - Cashier's handlers assume the payload always carries subscription items.
 *  - Cashier's handlers write the cancellation date from the Stripe API rather
 *    than from the cancellation date Stripe already sent in the payload.
 *
 * Every delivery is also claimed through the processed events table, so a
 * retried delivery is recognised and skipped instead of being applied twice.
 *
 * Nothing in a payload is trusted on its own. The delivery is only applied
 * when its signature verifies, and the subscription is only written when it
 * belongs to an account that already exists.
 */
class CashierWebhookController extends BaseWebhookController
{
    /**
     * The webhook event types this endpoint acts on.
     *
     * @var list<string>
     */
    protected const SUBSCRIPTION_EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    /**
     * The account the delivery being handled belongs to.
     *
     * The parent controller's event handlers take only the payload, so the
     * account resolved once in handleWebhook() is held here and read by the
     * handlers. This keeps a delivery acting on exactly one account, and it
     * saves the account being looked up again for every handler.
     */
    protected ?User $webhookUser = null;

    /**
     * Handle a Stripe webhook delivery.
     *
     * The signature has already been verified by the middleware, so the
     * payload can be read.
     */
    public function __invoke(Request $request): Response
    {
        return $this->handleWebhook($request);
    }

    /**
     * Handle a Stripe webhook call.
     *
     * The signature has already been verified by the middleware, so the
     * payload can be read. The event is claimed before it is applied, which
     * makes a retried delivery a no-op.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return $this->successMethod();
        }

        $type = $payload['type'] ?? '';

        if (! in_array($type, self::SUBSCRIPTION_EVENTS, true)) {
            // Any other event is acknowledged without being acted on. Stripe
            // only needs a successful response to stop retrying.
            return $this->successMethod();
        }

        $eventId = $payload['id'] ?? '';

        // Any account resolved by an earlier delivery on this controller is
        // cleared, so a handler can only ever act on the account this
        // delivery names.
        $this->webhookUser = null;

        $user = $this->userForPayload($payload);

        if (! ProcessedWebhookEvents::claim($eventId, $type, $user?->id)) {
            // The event has already been handled, so this delivery is ignored.
            return $this->successMethod();
        }

        if ($user === null) {
            // The event belongs to no known account. There is nothing to
            // apply, and the event stays recorded so it is not retried.
            return $this->successMethod();
        }

        // The handlers act on the account resolved above, so a delivery can
        // never touch an account other than the one it names.
        $this->webhookUser = $user;

        $method = 'handle'.Str::studly(str_replace('.', '_', $type));

        if (! method_exists($this, $method)) {
            return $this->successMethod();
        }

        $this->{$method}($payload);

        $this->syncPlan($user->fresh());

        return $this->successMethod();
    }

    /**
     * Move the account's stored plan to match its live subscription.
     *
     * The stored plan records what was last applied to the account. It is
     * moved to Pro when the account holds a valid Pro subscription, and back
     * to Free when it does not, so a cancelled or failed subscription leaves
     * the account on Free.
     *
     * Nothing is deleted when an account is downgraded. The account keeps its
     * spaces and its testimonials; it simply may not create new ones beyond
     * the Free limits, which the plan limit checks enforce.
     */
    protected function syncPlan(User $user): void
    {
        $plan = $user->effectivePlan();

        if ($user->plan_id === $plan->getKey()) {
            return;
        }

        $user->forceFill(['plan_id' => $plan->getKey()])->saveQuietly();
    }

    /**
     * The account the delivery being handled belongs to.
     *
     * The account is resolved once, in handleWebhook(), before any handler
     * runs. A handler is therefore never applied to an account it did not
     * resolve, and no handler has to look the account up again.
     */
    protected function userForThisDelivery(): ?User
    {
        return $this->webhookUser;
    }

    /**
     * Apply a subscription that was created.
     *
     * The subscription is written with the price Stripe sent, so the account
     * only gains Pro access when the price is the configured Pro price.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        $user = $this->userForThisDelivery();

        if ($user === null) {
            return $this->successMethod();
        }

        $this->syncSubscription($payload['data']['object'] ?? [], $user);
    }

    /**
     * Apply a change to an existing subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        $user = $this->userForThisDelivery();

        if ($user === null) {
            return $this->successMethod();
        }

        $data = $payload['data']['object'] ?? [];

        if (($data['status'] ?? null) === StripeSubscription::STATUS_INCOMPLETE_EXPIRED) {
            // The subscription never completed, so it is removed rather than
            // left behind as a half-finished record.
            $this->removeSubscription($data, $user);

            return $this->successMethod();
        }

        $this->syncSubscription($data, $user);
    }

    /**
     * Apply the removal of a subscription.
     *
     * The subscription is marked as canceled with the date Stripe sent, so the
     * account keeps its Pro access until the period it was paid for ends.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        $user = $this->userForThisDelivery();

        if ($user === null) {
            return $this->successMethod();
        }

        $data = $payload['data']['object'] ?? [];

        $subscription = $user->subscriptions()
            ->where('stripe_id', $data['id'] ?? '')
            ->first();

        if ($subscription === null) {
            return $this->successMethod();
        }

        $subscription->forceFill([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => $this->cancellationDate($data),
        ])->save();
    }

    /**
     * Write the subscription and its items from the payload.
     *
     * The record is matched on the Stripe subscription identifier, so a
     * repeated delivery updates the same row rather than creating a second
     * one. Everything written comes from the payload Stripe signed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncSubscription(array $data, User $user): void
    {
        $stripeId = $data['id'] ?? null;

        if (! is_string($stripeId) || $stripeId === '') {
            return;
        }

        $items = $this->itemsFrom($data);

        $subscription = $user->subscriptions()->updateOrCreate([
            'stripe_id' => $stripeId,
        ], [
            'type' => $data['metadata']['type'] ?? $data['metadata']['name'] ?? 'default',
            'stripe_status' => $data['status'] ?? StripeSubscription::STATUS_INCOMPLETE,
            'stripe_price' => $this->priceFrom($items),
            'quantity' => $this->quantityFrom($items),
            'trial_ends_at' => $this->trialEndFrom($data),
            'ends_at' => $this->cancellationDate($data),
        ]);

        foreach ($items as $item) {
            if (! isset($item['id'], $item['price']['id'])) {
                continue;
            }

            $subscription->items()->updateOrCreate([
                'stripe_id' => $item['id'],
            ], [
                'stripe_product' => $item['price']['product'] ?? null,
                'stripe_price' => $item['price']['id'],
                'quantity' => $item['quantity'] ?? null,
            ]);
        }
    }

    /**
     * Remove a subscription that never completed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function removeSubscription(array $data, User $user): void
    {
        $stripeId = $data['id'] ?? null;

        if (! is_string($stripeId) || $stripeId === '') {
            return;
        }

        $subscription = $user->subscriptions()
            ->where('stripe_id', $stripeId)
            ->first();

        if ($subscription === null) {
            return;
        }

        $subscription->items()->delete();
        $subscription->delete();
    }

    /**
     * The account the event belongs to.
     *
     * The customer identifier in a signed Stripe payload is the only thing
     * that ties the event to an account, and it is looked up rather than
     * taken from the request.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function userForPayload(array $payload): ?User
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! is_string($customerId) || $customerId === '') {
            return null;
        }

        return User::query()
            ->where('stripe_id', $customerId)
            ->first();
    }

    /**
     * The subscription items in the payload.
     *
     * A payload without items is not an error: Stripe sends one on some
     * events, and the subscription is still recorded.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected function itemsFrom(array $data): array
    {
        $items = $data['items']['data'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    /**
     * The single price of the subscription, when it has exactly one.
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function priceFrom(array $items): ?string
    {
        if (count($items) !== 1) {
            return null;
        }

        $price = $items[0]['price']['id'] ?? null;

        return is_string($price) ? $price : null;
    }

    /**
     * The quantity of the subscription, when it has exactly one item.
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function quantityFrom(array $items): ?int
    {
        if (count($items) !== 1) {
            return null;
        }

        $quantity = $items[0]['quantity'] ?? null;

        return is_numeric($quantity) ? (int) $quantity : null;
    }

    /**
     * The end of the trial, when the payload carries one.
     *
     * @param  array<string, mixed>  $data
     */
    protected function trialEndFrom(array $data): ?Carbon
    {
        $trialEnd = $data['trial_end'] ?? null;

        if (! is_numeric($trialEnd)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $trialEnd);
    }

    /**
     * The date the subscription ends, from the payload.
     *
     * A subscription cancelled at period end carries the date the period
     * ends. One that was canceled outright carries the date it was canceled.
     * A subscription that was not canceled carries no date at all.
     *
     * @param  array<string, mixed>  $data
     */
    protected function cancellationDate(array $data): ?Carbon
    {
        if (($data['cancel_at_period_end'] ?? false) === true) {
            $periodEnd = $data['current_period_end'] ?? null;

            return is_numeric($periodEnd)
                ? Carbon::createFromTimestamp((int) $periodEnd)
                : null;
        }

        foreach (['cancel_at', 'canceled_at'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return Carbon::createFromTimestamp((int) $data[$key]);
            }
        }

        return null;
    }
}
