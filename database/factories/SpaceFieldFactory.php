<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\SpaceField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaceField>
 */
class SpaceFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->randomElement(array_keys(SpaceField::KEYS));

        return [
            'space_id' => Space::factory(),
            'key' => $key,
            'label' => SpaceField::KEYS[$key]['label'],
            'enabled' => SpaceField::KEYS[$key]['enabled'],
            'required' => SpaceField::KEYS[$key]['required'],
            'position' => 0,
        ];
    }
}
