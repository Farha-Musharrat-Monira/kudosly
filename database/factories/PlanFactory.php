<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'price_cents' => 0,
            'currency' => 'USD',
            'stripe_price_id' => null,
            'max_spaces' => 3,
            'max_testimonials_per_space' => 100,
            'max_testimonials' => null,
            'is_active' => true,
        ];
    }

    /**
     * The documented Free plan limits.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Free',
            'slug' => 'free',
            'price_cents' => 0,
            'max_spaces' => 3,
            'max_testimonials_per_space' => 100,
        ]);
    }

    /**
     * The documented Pro plan limits and price.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pro',
            'slug' => 'pro',
            'price_cents' => 99900,
            'max_spaces' => 25,
            'max_testimonials_per_space' => 1000,
        ]);
    }
}
