<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
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
            'summary' => $this->faker->sentence(10),
            'status' => 'published',
            'published_at' => $this->faker->dateTimeThisYear(),
            'views_count' => $this->faker->numberBetween(0, 1000),
            'user_id' => \App\Models\User::factory(),
        ];
    }
}
