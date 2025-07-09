<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Analytics>
 */
class AnalyticsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => $this->faker->boolean(30) ? User::factory() : null,
            'type' => 'page_view',
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'referer' => $this->faker->boolean(70) ? $this->faker->url() : null,
            'session_id' => $this->faker->uuid(),
            'page_url' => $this->faker->url(),
            'page_title' => $this->faker->sentence(4),
            'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'browser_version' => $this->faker->randomFloat(1, 70, 120),
            'platform' => $this->faker->randomElement(['Windows', 'macOS', 'Linux', 'Android', 'iOS']),
            'device_type' => $this->faker->randomElement(['desktop', 'mobile', 'tablet']),
            'country' => $this->faker->country(),
            'city' => $this->faker->city(),
        ];
    }

    /**
     * Indicate that the analytics record is a page view.
     */
    public function pageView(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'page_view',
        ]);
    }

    /**
     * Indicate that the analytics record is a search.
     */
    public function search(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'search',
            'post_id' => null,
            'search_query' => $this->faker->words(2, true),
            'search_results_count' => $this->faker->numberBetween(0, 100),
            'search_type' => $this->faker->randomElement(['all', 'post', 'page', 'tag', 'category']),
        ]);
    }

    /**
     * Indicate that the analytics record is from mobile.
     */
    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => 'mobile',
            'platform' => $this->faker->randomElement(['Android', 'iOS']),
        ]);
    }

    /**
     * Indicate that the analytics record is from today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subMinutes($this->faker->numberBetween(0, 1440)),
        ]);
    }

    /**
     * Indicate that the analytics record is from a specific referer.
     */
    public function fromReferer(string $referer): static
    {
        return $this->state(fn (array $attributes) => [
            'referer' => $referer,
        ]);
    }
}