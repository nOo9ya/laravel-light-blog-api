<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'type' => 'post',
            'parent_id' => null,
            'order' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the category is for posts.
     */
    public function forPosts(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'post',
        ]);
    }

    /**
     * Indicate that the category is for pages.
     */
    public function forPages(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'page',
        ]);
    }

    /**
     * Indicate that the category is for both posts and pages.
     */
    public function forBoth(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'both',
        ]);
    }

    /**
     * Indicate that the category is a child category.
     */
    public function child($parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }
}