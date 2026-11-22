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

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        return $this->plan->isFree();
    }

    /**
     * How many spaces the current plan allows.
     */
    public function spaceQuota(): int
    {
        return (int) $this->plan->max_spaces;
    }

    /**
     * How many testimonials each space may hold on the current plan.
     */
    public function testimonialQuota(): int
    {
        return (int) $this->plan->max_testimonials_per_space;
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
