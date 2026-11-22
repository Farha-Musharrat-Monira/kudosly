<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'title' => fake()->sentence(4),
            'subtitle' => fake()->sentence(8),
            'testimonial_request' => fake()->sentence(12),
            'theme' => Space::DEFAULT_THEME,
            'collect_rating' => false,
            'is_published' => false,
        ];
    }

    /**
     * Use a specific theme.
     */
    public function theme(string $theme): static
    {
        return $this->state(fn (array $attributes) => [
            'theme' => $theme,
        ]);
    }

    /**
     * Collect a 1-5 star rating.
     */
    public function withRating(): static
    {
        return $this->state(fn (array $attributes) => [
            'collect_rating' => true,
        ]);
    }
}
