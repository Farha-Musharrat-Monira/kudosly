<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Widget>
 */
class WidgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The public identifier is generated the same way the model generates
     * it, so a factory-made widget is indistinguishable from one the
     * application creates.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'public_id' => Widget::generatePublicId(),
            'layout' => Widget::DEFAULT_LAYOUT,
            'theme' => Widget::DEFAULT_THEME,
            'background_color' => Widget::DEFAULT_BACKGROUND_COLOR,
            'has_animation' => true,
            'display_limit' => Widget::DEFAULT_DISPLAY_LIMIT,
        ];
    }

    /**
     * Use a specific layout.
     */
    public function layout(string $layout): static
    {
        return $this->state(fn (array $attributes) => [
            'layout' => $layout,
        ]);
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
     * Turn the card animation off.
     */
    public function withoutAnimation(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_animation' => false,
        ]);
    }
}
