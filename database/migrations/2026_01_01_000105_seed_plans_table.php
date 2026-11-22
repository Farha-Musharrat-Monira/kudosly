<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Free and Pro plans are seeded by a migration rather than only by
     * a seeder so the entitlement rows exist in every environment,
     * including the in-memory database used by the test suite.
     */
    public function up(): void
    {
        DB::table('plans')->upsert(
            [
                [
                    'name' => 'Free',
                    'slug' => 'free',
                    'description' => 'Everything you need to start collecting testimonials.',
                    'price_cents' => 0,
                    'currency' => 'USD',
                    'stripe_price_id' => null,
                    'max_spaces' => 3,
                    'max_testimonials_per_space' => 100,
                    'max_testimonials' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Pro',
                    'slug' => 'pro',
                    'description' => 'For teams collecting testimonials at scale.',
                    'price_cents' => 99900,
                    'currency' => 'USD',
                    'stripe_price_id' => null,
                    'max_spaces' => 25,
                    'max_testimonials_per_space' => 1000,
                    'max_testimonials' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['slug'],
            [
                'name',
                'description',
                'price_cents',
                'currency',
                'stripe_price_id',
                'max_spaces',
                'max_testimonials_per_space',
                'max_testimonials',
                'is_active',
                'updated_at',
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('plans')->whereIn('slug', ['free', 'pro'])->delete();
    }
};
