<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Stripe webhook event that has already been processed.
 *
 * Stripe retries a webhook until it is acknowledged, so the same event can
 * arrive more than once. Recording the event identifier here lets a repeat
 * delivery be recognised and skipped, so the subscription is never written
 * twice.
 */
#[Fillable(['stripe_event_id', 'type', 'user_id', 'processed_at'])]
#[Hidden(['stripe_event_id'])]
class StripeWebhookEvent extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stripe_webhook_events';

    /**
     * The event identifier is the primary key, so it is never auto-incremented.
     *
     * @var string
     */
    protected $primaryKey = 'stripe_event_id';

    /**
     * The primary key is a string rather than an integer.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The primary key is never incremented.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The account the event was processed for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
