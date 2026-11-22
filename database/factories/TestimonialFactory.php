<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'user_id' => fn (array $attributes): User => Space::find($attributes['space_id'])?->owner ?? User::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'body' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
            'consent_to_share' => false,
            'status' => Testimonial::STATUS_PENDING,
            'is_favorite' => false,
            'is_heart' => false,
        ];
    }

    /**
     * Approve the testimonial.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Testimonial::STATUS_APPROVED,
        ]);
    }

    /**
     * Mark the testimonial as consented for public display.
     */
    public function consented(): static
    {
        return $this->state(fn (array $attributes) => [
            'consent_to_share' => true,
        ]);
    }
}
