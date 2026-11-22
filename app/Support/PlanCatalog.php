<?php

namespace App\Support;

/**
 * The single source of truth for the plans Kudosly sells.
 *
 * The values here mirror PRD section 10: the Free plan is free with three
 * spaces and a hundred testimonials per space, and the Pro plan costs
 * $999.00 USD per month with twenty-five spaces and a thousand testimonials
 * per space. The persisted "plans" table is seeded from these definitions, so
 * the limits a customer is actually charged against can never drift from the
 * limits quoted in the interface.
 */
final class PlanCatalog
{
    /**
     * The Free plan.
     *
     * @var array<string, int|string>
     */
    public const FREE = [
        'name' => 'Free',
        'slug' => 'free',
        'description' => 'Everything you need to start collecting testimonials.',
        'price_cents' => 0,
        'currency' => 'USD',
        'max_spaces' => 3,
        'max_testimonials_per_space' => 100,
    ];

    /**
     * The Pro plan: $999.00 USD, billed monthly.
     *
     * @var array<string, int|string>
     */
    public const PRO = [
        'name' => 'Pro',
        'slug' => 'pro',
        'description' => 'For teams collecting testimonials at scale.',
        'price_cents' => 99900,
        'currency' => 'USD',
        'max_spaces' => 25,
        'max_testimonials_per_space' => 1000,
    ];

    /**
     * Every plan the catalog knows about, keyed by slug.
     *
     * @return array<string, array<string, int|string>>
     */
    public static function all(): array
    {
        return [
            self::FREE['slug'] => self::FREE,
            self::PRO['slug'] => self::PRO,
        ];
    }

    /**
     * The definition for a plan slug.
     *
     * @return array<string, int|string>
     */
    public static function forSlug(string $slug): array
    {
        return self::all()[$slug] ?? self::FREE;
    }

    /**
     * The price of a plan, in cents.
     */
    public static function priceCents(string $slug): int
    {
        return (int) self::forSlug($slug)['price_cents'];
    }

    /**
     * How many spaces a plan allows.
     */
    public static function maxSpaces(string $slug): int
    {
        return (int) self::forSlug($slug)['max_spaces'];
    }

    /**
     * How many testimonials each space may hold on a plan.
     */
    public static function maxTestimonialsPerSpace(string $slug): int
    {
        return (int) self::forSlug($slug)['max_testimonials_per_space'];
    }

    /**
     * A human readable price for a plan, for example "Free" or "$999.00/mo".
     */
    public static function priceLabel(string $slug): string
    {
        $cents = self::priceCents($slug);

        if ($cents === 0) {
            return 'Free';
        }

        return '$'.number_format($cents / 100, 2).'/mo';
    }
}
