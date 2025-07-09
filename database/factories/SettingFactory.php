<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Setting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $settingTypes = [
            'site_title' => '내 블로그',
            'site_description' => '개발 블로그입니다.',
            'site_keywords' => 'Laravel, PHP, 개발, 블로그',
            'site_author' => $this->faker->name(),
            'site_email' => $this->faker->safeEmail(),
            'site_url' => $this->faker->url(),
            'posts_per_page' => $this->faker->numberBetween(5, 20),
            'comments_per_page' => $this->faker->numberBetween(10, 50),
            'enable_comments' => $this->faker->boolean(80),
            'enable_guest_comments' => $this->faker->boolean(60),
            'auto_approve_comments' => $this->faker->boolean(40),
            'theme_name' => 'default',
            'timezone' => 'Asia/Seoul',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'language' => 'ko',
            'analytics_code' => 'GA-' . $this->faker->uuid(),
            'social_facebook' => $this->faker->url(),
            'social_twitter' => $this->faker->url(),
            'social_instagram' => $this->faker->url(),
            'social_github' => $this->faker->url(),
            'meta_og_image' => $this->faker->imageUrl(1200, 630),
            'meta_twitter_card' => 'summary_large_image',
            'cache_enabled' => $this->faker->boolean(70),
            'cache_ttl' => $this->faker->numberBetween(300, 3600),
            'image_quality' => $this->faker->numberBetween(70, 95),
            'image_max_width' => $this->faker->numberBetween(800, 2000),
            'image_max_height' => $this->faker->numberBetween(600, 1500),
        ];

        $key = $this->faker->randomElement(array_keys($settingTypes));
        $value = $settingTypes[$key];

        return [
            'key' => $key,
            'value' => is_bool($value) ? json_encode($value) : (string) $value,
            'type' => $this->getTypeFromValue($value),
            'group' => $this->getGroupFromKey($key),
            'description' => $this->getDescriptionFromKey($key),
            'is_public' => $this->faker->boolean(60),
        ];
    }

    /**
     * 값의 타입 결정
     */
    private function getTypeFromValue($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        } else {
            return 'string';
        }
    }

    /**
     * 키에 따른 그룹 결정
     */
    private function getGroupFromKey(string $key): string
    {
        if (str_starts_with($key, 'site_')) {
            return 'site';
        } elseif (str_starts_with($key, 'social_')) {
            return 'social';
        } elseif (str_starts_with($key, 'meta_')) {
            return 'seo';
        } elseif (str_starts_with($key, 'cache_')) {
            return 'cache';
        } elseif (str_starts_with($key, 'image_')) {
            return 'image';
        } elseif (str_contains($key, 'comment')) {
            return 'comment';
        } else {
            return 'general';
        }
    }

    /**
     * 키에 따른 설명 생성
     */
    private function getDescriptionFromKey(string $key): string
    {
        $descriptions = [
            'site_title' => '사이트 제목',
            'site_description' => '사이트 설명',
            'site_keywords' => '사이트 키워드 (쉼표로 구분)',
            'site_author' => '사이트 관리자',
            'site_email' => '사이트 이메일',
            'site_url' => '사이트 URL',
            'posts_per_page' => '페이지당 포스트 수',
            'comments_per_page' => '페이지당 댓글 수',
            'enable_comments' => '댓글 기능 활성화',
            'enable_guest_comments' => '비회원 댓글 허용',
            'auto_approve_comments' => '댓글 자동 승인',
            'theme_name' => '현재 테마',
            'timezone' => '시간대',
            'date_format' => '날짜 형식',
            'time_format' => '시간 형식',
            'language' => '언어',
            'analytics_code' => 'Google Analytics 코드',
            'social_facebook' => '페이스북 URL',
            'social_twitter' => '트위터 URL',
            'social_instagram' => '인스타그램 URL',
            'social_github' => '깃허브 URL',
            'meta_og_image' => '기본 OG 이미지',
            'meta_twitter_card' => '트위터 카드 타입',
            'cache_enabled' => '캐시 활성화',
            'cache_ttl' => '캐시 유효시간 (초)',
            'image_quality' => '이미지 품질 (%)',
            'image_max_width' => '이미지 최대 너비',
            'image_max_height' => '이미지 최대 높이',
        ];

        return $descriptions[$key] ?? '설정 항목';
    }

    /**
     * 사이트 설정
     */
    public function site(): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => 'site',
            'is_public' => true,
        ]);
    }

    /**
     * 소셜 미디어 설정
     */
    public function social(): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => 'social',
            'is_public' => true,
        ]);
    }

    /**
     * SEO 설정
     */
    public function seo(): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => 'seo',
            'is_public' => false,
        ]);
    }

    /**
     * 캐시 설정
     */
    public function cache(): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => 'cache',
            'is_public' => false,
        ]);
    }

    /**
     * 공개 설정
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * 비공개 설정
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
}