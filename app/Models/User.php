<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'stripe_id', 'billing_email'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'plan_expires_at' => 'datetime',
        ];
    }

    /**
     * Every account starts on the Free plan, whichever way the account was
     * created. The plan column is deliberately not fillable, so it is only
     * ever assigned by the application.
     */
    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if (blank($user->plan_id)) {
                $user->assignFreePlan();
            }
        });
    }

    /**
     * The plan the account is currently entitled to.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withDefault(fn (): Plan => Plan::free());
    }

    /**
     * The spaces owned by this account.
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * The testimonials collected across this account's spaces.
     */
    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    /**
     * Assign the persisted Free plan to this account.
     *
     * The plan row must exist: a missing row is a deployment fault, so the
     * account is left untouched rather than being given a null plan id.
     */
    public function assignFreePlan(): bool
    {
        $plan = Plan::query()->where('slug', 'free')->first();

        if ($plan === null) {
            return false;
        }

        $this->forceFill(['plan_id' => $plan->getKey()])->saveQuietly();

        return true;
    }

    public function onFreePlan(): bool
    {
        return ! $this->subscribedToPro();
    }

    /**
     * The plan the account is actually entitled to right now.
     *
     * The stored plan relationship records what was last applied to the
     * account, but entitlement is decided by the live Stripe subscription so
     * a lapsed, unpaid or incomplete subscription can never keep granting
     * Pro access. Anything that is not a valid Pro subscription falls back to
     * the Free plan.
     */
    public function effectivePlan(): Plan
    {
        return $this->subscribedToPro() ? Plan::pro() : Plan::free();
    }

    /**
     * Whether the account currently holds a valid Pro subscription.
     *
     * A subscription grants Pro access while it is active, and also while it
     * sits inside its grace period after being cancelled at period end.
     * Past due, unpaid, incomplete, incomplete expired and fully expired
     * subscriptions do not grant access.
     */
    public function subscribedToPro(): bool
    {
        $subscription = $this->proSubscription();

        if ($subscription === null) {
            return false;
        }

        if (! $subscription->active() && ! $subscription->onGracePeriod()) {
            return false;
        }

        $price = $this->proPriceId();

        // With no configured Pro price there is nothing to match against, so
        // a subscription cannot be confirmed as being for the Pro plan.
        if ($price === null) {
            return false;
        }

        return $subscription->hasPrice($price);
    }

    /**
     * Whether the account's subscription is inside its grace period.
     *
     * A subscription cancelled at period end keeps working until the period
     * it was paid for has actually ended.
     */
    public function onGracePeriod(): bool
    {
        return $this->proSubscription()?->onGracePeriod() ?? false;
    }

    /**
     * The account's current subscription.
     *
     * The subscription is read straight from the database rather than from
     * Cashier's cached relation, so entitlement always reflects the state
     * that is stored right now rather than a snapshot taken earlier in the
     * request.
     */
    public function proSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('type', 'default')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * The Stripe Price ID for the Pro plan's recurring monthly price.
     *
     * The price is resolved on the server from configuration and is never
     * hardcoded or sent to the browser.
     */
    public function proPriceId(): ?string
    {
        $price = config('services.stripe.price_pro');

        return is_string($price) && $price !== '' ? $price : null;
    }

    /**
     * The email address Stripe should send invoices and receipts to.
     *
     * Accounts may set a dedicated billing email; everything else bills to
     * the account email address.
     */
    public function billingEmail(): ?string
    {
        return $this->billing_email ?: $this->email;
    }

    /**
     * How many spaces the current plan allows.
     */
    public function spaceQuota(): int
    {
        return (int) $this->effectivePlan()->max_spaces;
    }

    /**
     * How many testimonials each space may hold on the current plan.
     */
    public function testimonialQuota(): int
    {
        return (int) $this->effectivePlan()->max_testimonials_per_space;
    }

    /**
     * How many spaces the account already owns.
     */
    public function spacesUsed(): int
    {
        return $this->spaces()->count();
    }

    /**
     * Whether the account may create another space within its plan limits.
     */
    public function canCreateSpace(): bool
    {
        return $this->spacesUsed() < $this->spaceQuota();
    }

    /**
     * How many more spaces the account may create.
     */
    public function remainingSpaces(): int
    {
        return max(0, $this->spaceQuota() - $this->spacesUsed());
    }
}
