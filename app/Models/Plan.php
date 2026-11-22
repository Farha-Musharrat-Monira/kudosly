<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'price_cents',
    'currency',
    'stripe_price_id',
    'max_spaces',
    'max_testimonials_per_space',
    'max_testimonials',
    'is_active',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'max_spaces' => 'integer',
            'max_testimonials_per_space' => 'integer',
            'max_testimonials' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The accounts subscribed to this plan.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isFree(): bool
    {
        return $this->slug === 'free';
    }

    public function isPro(): bool
    {
        return $this->slug === 'pro';
    }

    /**
     * A human readable price, for example "Free" or "$999.00/mo".
     */
    public function priceLabel(): string
    {
        if ($this->price_cents === 0) {
            return 'Free';
        }

        return '$'.number_format($this->price_cents / 100, 2).'/mo';
    }

    /**
     * The Free plan. Falls back to an unsaved instance with the documented
     * Free limits when the seeded row is missing, so callers never have to
     * guard against a null plan.
     */
    public static function free(): self
    {
        return static::query()->where('slug', 'free')->first() ?? new static([
            'name' => 'Free',
            'slug' => 'free',
            'price_cents' => 0,
            'currency' => 'USD',
            'max_spaces' => 3,
            'max_testimonials_per_space' => 100,
            'is_active' => true,
        ]);
    }

    /**
     * The Pro plan. Falls back to an unsaved instance with the documented
     * Pro limits when the seeded row is missing.
     */
    public static function pro(): self
    {
        return static::query()->where('slug', 'pro')->first() ?? new static([
            'name' => 'Pro',
            'slug' => 'pro',
            'price_cents' => 99900,
            'currency' => 'USD',
            'max_spaces' => 25,
            'max_testimonials_per_space' => 1000,
            'is_active' => true,
        ]);
    }
}
