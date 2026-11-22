<?php

namespace App\Support;

use App\Models\StripeWebhookEvent;
use Illuminate\Database\QueryException;

/**
 * Keeps track of the Stripe webhook events that have already been handled.
 *
 * Stripe retries a webhook until it is acknowledged, so the same event can be
 * delivered more than once. The event identifier is inserted as the primary
 * key of the record, so the insert itself is the guard: the first delivery
 * succeeds and any repeat delivery collides and is refused by the database.
 * That makes the guard safe against two deliveries arriving at the same time,
 * which a check-then-write would not be.
 */
final class ProcessedWebhookEvents
{
    /**
     * Claim an event so it is processed exactly once.
     *
     * Returns true when this delivery is the one that should do the work, and
     * false when the event has already been handled.
     */
    public static function claim(string $eventId, string $type, ?int $userId = null): bool
    {
        if ($eventId === '') {
            // An event without an identifier cannot be guarded against a
            // repeat, so it is refused rather than processed twice.
            return false;
        }

        try {
            StripeWebhookEvent::query()->create([
                'stripe_event_id' => $eventId,
                'type' => $type,
                'user_id' => $userId,
                'processed_at' => now(),
            ]);
        } catch (QueryException) {
            // The event identifier is already recorded, so this is a repeat
            // delivery.
            return false;
        }

        return true;
    }

    /**
     * Whether an event has already been processed.
     */
    public static function has(string $eventId): bool
    {
        return StripeWebhookEvent::query()
            ->where('stripe_event_id', $eventId)
            ->exists();
    }

    /**
     * Attach the processed event to the account it belongs to.
     */
    public static function associateWithUser(string $eventId, int $userId): void
    {
        StripeWebhookEvent::query()
            ->where('stripe_event_id', $eventId)
            ->update(['user_id' => $userId]);
    }
}
