<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'content' => '<p>' . $this->faker->paragraphs(3, true) . '</p>',
            'excerpt' => $this->faker->sentence(10),
            'is_published' => true,
            'show_in_menu' => $this->faker->boolean(30),
            'order' => $this->faker->numberBetween(0, 100),
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
        ];
    }

    /**
     * Indicate that the page is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    /**
     * Indicate that the page is not published.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    /**
     * Indicate that the page should show in menu.
     */
    public function inMenu(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_in_menu' => true,
        ]);
    }
}