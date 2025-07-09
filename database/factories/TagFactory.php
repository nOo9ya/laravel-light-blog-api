<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'color' => $this->faker->hexColor(),
        ];
    }

    /**
     * Indicate that the tag has a specific color.
     */
    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $color,
        ]);
    }

    /**
     * Indicate that the tag is popular (for testing).
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement(['php', 'laravel', 'javascript', 'react', 'vue']),
        ]);
    }
}