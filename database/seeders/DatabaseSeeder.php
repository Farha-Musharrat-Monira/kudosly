<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The plan rows are also seeded by a migration so the entitlement data is
     * always present. This seeder keeps them in sync for local development.
     */
    public function run(): void
    {
        Plan::query()->updateOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'description' => 'Everything you need to start collecting testimonials.',
            'price_cents' => 0,
            'currency' => 'USD',
            'stripe_price_id' => null,
            'max_spaces' => 3,
            'max_testimonials_per_space' => 100,
            'max_testimonials' => null,
            'is_active' => true,
        ]);

        Plan::query()->updateOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'description' => 'For teams collecting testimonials at scale.',
            'price_cents' => 99900,
            'currency' => 'USD',
            'stripe_price_id' => null,
            'max_spaces' => 25,
            'max_testimonials_per_space' => 1000,
            'max_testimonials' => null,
            'is_active' => true,
        ]);
    }
}
