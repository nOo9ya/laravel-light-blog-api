<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isGuest = $this->faker->boolean(30); // 30% 확률로 비회원 댓글

        $baseData = [
            'post_id' => Post::factory(),
            'content' => $this->faker->realText($this->faker->numberBetween(50, 500)),
            'status' => $this->faker->randomElement(['pending', 'approved', 'spam']),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];

        if ($isGuest) {
            // 비회원 댓글
            return array_merge($baseData, [
                'user_id' => null,
                'guest_name' => $this->faker->name(),
                'guest_email' => $this->faker->safeEmail(),
                'guest_password' => Hash::make('password123'),
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
            ]);
        } else {
            // 회원 댓글
            return array_merge($baseData, [
                'user_id' => User::factory(),
                'guest_name' => null,
                'guest_email' => null,
                'guest_password' => null,
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
            ]);
        }
    }

    /**
     * 승인된 댓글 상태
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'approved_by' => User::factory(),
        ]);
    }

    /**
     * 대기 중인 댓글 상태
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    /**
     * 스팸 댓글 상태
     */
    public function spam(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'spam',
            'spam_score' => $this->faker->numberBetween(80, 100),
        ]);
    }

    /**
     * 비회원 댓글
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_name' => $this->faker->name(),
            'guest_email' => $this->faker->safeEmail(),
            'guest_password' => Hash::make('password123'),
        ]);
    }

    /**
     * 회원 댓글
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'guest_name' => null,
            'guest_email' => null,
            'guest_password' => null,
        ]);
    }

    /**
     * 대댓글
     */
    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Comment::factory(),
            'depth' => $this->faker->numberBetween(1, 3),
        ]);
    }

    /**
     * 최상위 댓글
     */
    public function topLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
            'depth' => 0,
        ]);
    }
}