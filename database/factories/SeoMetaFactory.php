<?php

namespace Database\Factories;

use App\Models\SeoMeta;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SeoMeta>
 */
class SeoMetaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SeoMeta::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'meta_title' => $this->faker->sentence(6),
            'meta_description' => $this->faker->text(160),
            'meta_keywords' => implode(', ', $this->faker->words(8)),
            'robots' => $this->faker->randomElement(['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow']),
            'canonical_url' => $this->faker->url(),
            
            // Open Graph 메타 데이터
            'og_title' => $this->faker->sentence(5),
            'og_description' => $this->faker->text(160),
            'og_image' => $this->faker->imageUrl(1200, 630),
            'og_url' => $this->faker->url(),
            'og_type' => $this->faker->randomElement(['article', 'website', 'blog']),
            'og_site_name' => $this->faker->company(),
            'og_locale' => 'ko_KR',
            
            // Twitter Card 메타 데이터
            'twitter_card' => $this->faker->randomElement(['summary', 'summary_large_image']),
            'twitter_title' => $this->faker->sentence(5),
            'twitter_description' => $this->faker->text(160),
            'twitter_image' => $this->faker->imageUrl(1200, 630),
            'twitter_site' => '@' . $this->faker->userName(),
            'twitter_creator' => '@' . $this->faker->userName(),
            
            // JSON-LD 구조화 데이터
            'structured_data' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $this->faker->sentence(6),
                'description' => $this->faker->text(160),
                'author' => [
                    '@type' => 'Person',
                    'name' => $this->faker->name(),
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $this->faker->company(),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $this->faker->imageUrl(200, 200)
                    ]
                ],
                'datePublished' => $this->faker->dateTimeThisYear()->format('Y-m-d\TH:i:s\Z'),
                'dateModified' => $this->faker->dateTimeThisYear()->format('Y-m-d\TH:i:s\Z'),
                'image' => $this->faker->imageUrl(1200, 630),
                'url' => $this->faker->url(),
            ]),
            
            // 추가 메타 태그
            'additional_meta' => json_encode([
                'author' => $this->faker->name(),
                'copyright' => $this->faker->company(),
                'generator' => 'Laravel Blog System',
                'revisit-after' => '7 days',
                'rating' => 'general',
                'distribution' => 'global',
            ]),
        ];
    }

    /**
     * 기본 SEO 설정
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'robots' => 'index,follow',
            'og_type' => 'article',
            'twitter_card' => 'summary_large_image',
        ]);
    }

    /**
     * 완전한 SEO 설정
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_title' => $this->faker->sentence(6),
            'meta_description' => $this->faker->text(155),
            'meta_keywords' => implode(', ', $this->faker->words(10)),
            'robots' => 'index,follow',
            'og_title' => $this->faker->sentence(5),
            'og_description' => $this->faker->text(155),
            'og_image' => $this->faker->imageUrl(1200, 630),
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $this->faker->sentence(5),
            'twitter_description' => $this->faker->text(155),
            'twitter_image' => $this->faker->imageUrl(1200, 630),
        ]);
    }

    /**
     * 최소한의 SEO 설정
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_title' => $this->faker->sentence(4),
            'meta_description' => $this->faker->text(120),
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'twitter_card' => null,
            'twitter_title' => null,
            'twitter_description' => null,
            'twitter_image' => null,
            'structured_data' => null,
            'additional_meta' => null,
        ]);
    }

    /**
     * Open Graph 설정
     */
    public function withOpenGraph(): static
    {
        return $this->state(fn (array $attributes) => [
            'og_title' => $this->faker->sentence(5),
            'og_description' => $this->faker->text(160),
            'og_image' => $this->faker->imageUrl(1200, 630),
            'og_type' => 'article',
            'og_site_name' => $this->faker->company(),
        ]);
    }

    /**
     * Twitter Card 설정
     */
    public function withTwitterCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $this->faker->sentence(5),
            'twitter_description' => $this->faker->text(160),
            'twitter_image' => $this->faker->imageUrl(1200, 630),
            'twitter_site' => '@' . $this->faker->userName(),
            'twitter_creator' => '@' . $this->faker->userName(),
        ]);
    }

    /**
     * 구조화 데이터 포함
     */
    public function withStructuredData(): static
    {
        return $this->state(fn (array $attributes) => [
            'structured_data' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $this->faker->sentence(6),
                'description' => $this->faker->text(160),
                'author' => [
                    '@type' => 'Person',
                    'name' => $this->faker->name(),
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $this->faker->company(),
                ],
                'datePublished' => now()->format('Y-m-d\TH:i:s\Z'),
                'dateModified' => now()->format('Y-m-d\TH:i:s\Z'),
            ]),
        ]);
    }

    /**
     * noindex 설정
     */
    public function noIndex(): static
    {
        return $this->state(fn (array $attributes) => [
            'robots' => 'noindex,follow',
        ]);
    }

    /**
     * nofollow 설정
     */
    public function noFollow(): static
    {
        return $this->state(fn (array $attributes) => [
            'robots' => 'index,nofollow',
        ]);
    }
}