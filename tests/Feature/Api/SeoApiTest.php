<?php

/**
 * 테스트 목적: SEO 메타 시스템 테스트
 * 테스트 시나리오: SEO 메타 정보 CRUD, 미리보기, 분석, 사이트맵
 * 기대 결과: SEO 최적화 기능이 정상 작동
 * 관련 비즈니스 규칙: 작성자/관리자만 수정 가능, JSON-LD 구조화 데이터 생성
 */

use App\Models\User;
use App\Models\Post;
use App\Models\SeoMeta;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Given: 테스트용 사용자 및 포스트 생성
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->authorUser = User::factory()->create(['role' => 'author']);
    $this->normalUser = User::factory()->create(['role' => 'user']);
    
    $this->post = Post::factory()->create([
        'user_id' => $this->authorUser->id,
        'title' => 'Laravel 11 완벽 가이드',
        'content' => 'Laravel 11의 새로운 기능들을 상세히 알아봅시다.',
        'summary' => 'Laravel 11 새 기능 소개',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    
    // JWT 토큰 생성
    $this->adminToken = auth()->login($this->adminUser);
    $this->authorToken = auth()->login($this->authorUser);
    $this->userToken = auth()->login($this->normalUser);
});

test('작성자가_포스트_SEO_메타를_조회할_수_있다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    // When: SEO 메타 조회 API 호출
    $response = $this->getJson("/api/v1/seo/post/{$this->post->id}", [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: SEO 메타 정보 반환 (없으면 기본값)
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'post_id',
                'og_title',
                'og_description',
                'og_image',
                'og_type',
                'twitter_card',
                'twitter_title',
                'twitter_description',
                'canonical_url',
                'meta_keywords',
                'robots',
                'custom_meta'
            ]
        ]);
    
    $data = $response->json('data');
    expect($data['post_id'])->toBe($this->post->id);
    expect($data['og_title'])->toBe('Laravel 11 완벽 가이드'); // 기본값
});

test('관리자가_포스트_SEO_메타를_수정할_수_있다', function () {
    // Given: 관리자로 인증
    $this->actingAs($this->adminUser);
    
    $seoData = [
        'og_title' => '커스텀 OG 제목',
        'og_description' => '커스텀 OG 설명입니다.',
        'og_image' => '/storage/custom-og.jpg',
        'meta_keywords' => 'Laravel, PHP, 튜토리얼',
        'robots' => 'index,follow',
        'canonical_url' => 'https://example.com/laravel-guide',
        'twitter_title' => '트위터 제목',
        'twitter_description' => '트위터 설명',
        'custom_meta' => [
            'author' => 'John Doe',
            'article_section' => 'Technology'
        ]
    ];
    
    // When: SEO 메타 수정 API 호출
    $response = $this->postJson("/api/v1/seo/post/{$this->post->id}", $seoData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: SEO 메타가 성공적으로 저장됨
    $response->assertStatus(201);
    expect($response->json('data.og_title'))->toBe('커스텀 OG 제목');
    expect($response->json('data.og_description'))->toBe('커스텀 OG 설명입니다.');
    
    $this->assertDatabaseHas('seo_metas', [
        'post_id' => $this->post->id,
        'og_title' => '커스텀 OG 제목',
        'og_description' => '커스텀 OG 설명입니다.',
        'meta_keywords' => 'Laravel, PHP, 튜토리얼',
    ]);
});

test('다른_사용자는_SEO_메타를_수정할_수_없다', function () {
    // Given: 일반 사용자로 인증
    $this->actingAs($this->normalUser);
    
    $seoData = [
        'og_title' => '해킹 시도',
    ];
    
    // When: 다른 사용자의 포스트 SEO 메타 수정 시도
    $response = $this->postJson("/api/v1/seo/post/{$this->post->id}", $seoData, [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 권한 부족으로 실패
    $response->assertStatus(403);
});

test('SEO_미리보기를_생성할_수_있다', function () {
    // Given: SEO 메타가 있는 포스트
    SeoMeta::create([
        'post_id' => $this->post->id,
        'og_title' => 'Laravel 11 완벽 가이드',
        'og_description' => 'Laravel 11의 새로운 기능들을 상세히 알아봅시다.',
        'og_image' => '/storage/laravel-guide.jpg',
        'meta_keywords' => 'Laravel, PHP, 프레임워크',
    ]);
    
    // When: SEO 미리보기 API 호출
    $response = $this->getJson("/api/v1/seo/preview/{$this->post->slug}");
    
    // Then: SEO 미리보기 데이터 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'meta_tags',
                'json_ld',
                'seo_data',
                'preview' => [
                    'google_preview',
                    'facebook_preview',
                    'twitter_preview'
                ],
                'analysis' => [
                    'title_length',
                    'description_length',
                    'keywords_count',
                    'has_image',
                    'recommendations'
                ]
            ]
        ]);
    
    $data = $response->json('data');
    expect($data['meta_tags'])->toBeString();
    expect($data['json_ld'])->toBeArray();
    expect($data['preview']['google_preview'])->toBeArray();
    expect($data['analysis']['title_length'])->toBeInt();
});

test('사이트맵_데이터를_조회할_수_있다', function () {
    // Given: 발행된 포스트들 추가 생성
    Post::factory()->count(3)->create([
        'status' => 'published',
        'published_at' => now()->subDays(2),
    ]);
    
    // When: 사이트맵 데이터 조회
    $response = $this->getJson('/api/v1/seo/sitemap');
    
    // Then: 사이트맵 URL 데이터 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'urls' => [
                    '*' => [
                        'url',
                        'lastmod',
                        'changefreq',
                        'priority'
                    ]
                ],
                'total_urls',
                'generated_at'
            ]
        ]);
    
    $data = $response->json('data');
    expect($data['total_urls'])->toBeGreaterThan(0);
    expect($data['urls'])->toBeArray();
    
    // 홈페이지 URL이 포함되어 있는지 확인
    $homeUrl = collect($data['urls'])->first(function ($url) {
        return $url['priority'] === '1.0';
    });
    expect($homeUrl)->not->toBeNull();
});

test('SEO_분석을_수행할_수_있다', function () {
    // Given: 분석할 콘텐츠 데이터
    $this->actingAs($this->authorUser);
    
    $analysisData = [
        'title' => 'Laravel 11 완벽 가이드 - 새로운 기능과 개선사항',
        'description' => 'Laravel 11에서 도입된 새로운 기능들과 개선사항들을 상세히 알아보고, 실제 프로젝트에 어떻게 적용할 수 있는지 살펴봅시다.',
        'content' => str_repeat('Laravel 11은 많은 새로운 기능을 제공합니다. ', 50),
        'keywords' => 'Laravel, PHP, 프레임워크, 웹개발, API',
        'url' => 'https://example.com/laravel-11-guide'
    ];
    
    // When: SEO 분석 API 호출
    $response = $this->postJson('/api/v1/seo/analyze', $analysisData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: SEO 분석 결과 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'analysis' => [
                    'title' => [
                        'text',
                        'length',
                        'optimal',
                        'score'
                    ],
                    'description' => [
                        'text',
                        'length',
                        'optimal',
                        'score'
                    ],
                    'keywords' => [
                        'text',
                        'count',
                        'optimal'
                    ],
                    'content' => [
                        'length',
                        'word_count',
                        'optimal'
                    ]
                ],
                'overall_score',
                'grade',
                'recommendations',
                'analyzed_at'
            ]
        ]);
    
    $analysis = $response->json('data.analysis');
    expect($analysis['title']['length'])->toBe(strlen($analysisData['title']));
    expect($analysis['title']['optimal'])->toBeTrue(); // 30-60자 범위
    expect($analysis['description']['optimal'])->toBeTrue(); // 120-160자 범위
    
    $grade = $response->json('data.grade');
    expect($grade)->toBeIn(['A', 'B', 'C', 'D']);
});

test('SEO_유효성_검사가_작동한다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $invalidSeoData = [
        'og_title' => str_repeat('a', 300), // 너무 긴 제목
        'og_description' => str_repeat('b', 600), // 너무 긴 설명
        'canonical_url' => 'invalid-url', // 잘못된 URL
        'og_type' => 'invalid-type', // 잘못된 타입
    ];
    
    // When: 잘못된 데이터로 SEO 메타 수정 시도
    $response = $this->postJson("/api/v1/seo/post/{$this->post->id}", $invalidSeoData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 유효성 검사 오류 반환
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'og_title',
            'og_description', 
            'canonical_url',
            'og_type'
        ]);
});

test('SeoService_직접_테스트', function () {
    // Given: SeoService와 포스트
    $seoService = app(SeoService::class);
    
    // When: SEO 데이터 생성
    $seoData = SeoService::getPostSeoData($this->post);
    
    // Then: SEO 데이터 구조 확인
    expect($seoData)->toHaveKeys([
        'title',
        'description',
        'keywords',
        'image',
        'url',
        'type',
        'author',
        'published_time',
        'modified_time',
        'robots'
    ]);
    
    expect($seoData['title'])->toContain('Laravel 11 완벽 가이드');
    expect($seoData['type'])->toBe('article');
    expect($seoData['author'])->toBe($this->authorUser->name);
});

test('JSON_LD_구조화_데이터가_생성된다', function () {
    // Given: 포스트와 SeoService
    $jsonLd = SeoService::getJsonLd($this->post);
    
    // Then: JSON-LD 구조 확인
    expect($jsonLd)->toHaveKeys([
        '@context',
        '@type',
        'headline',
        'description',
        'author',
        'publisher',
        'datePublished',
        'dateModified',
        'url',
        'mainEntityOfPage'
    ]);
    
    expect($jsonLd['@context'])->toBe('https://schema.org');
    expect($jsonLd['@type'])->toBe('BlogPosting');
    expect($jsonLd['headline'])->toBe('Laravel 11 완벽 가이드');
    expect($jsonLd['author']['@type'])->toBe('Person');
    expect($jsonLd['publisher']['@type'])->toBe('Organization');
});

test('메타_태그_HTML이_생성된다', function () {
    // Given: SEO 데이터
    $seoData = [
        'title' => 'Laravel 11 완벽 가이드',
        'description' => 'Laravel 11의 새로운 기능들을 알아봅시다.',
        'keywords' => 'Laravel, PHP, 프레임워크',
        'image' => 'https://example.com/laravel-guide.jpg',
        'url' => 'https://example.com/laravel-11-guide',
        'type' => 'article',
        'author' => 'John Doe',
        'robots' => 'index,follow'
    ];
    
    // When: 메타 태그 HTML 생성
    $metaTags = SeoService::generateMetaTags($seoData);
    
    // Then: HTML 메타 태그 확인
    expect($metaTags)->toBeString();
    expect($metaTags)->toContain('name="description"');
    expect($metaTags)->toContain('name="keywords"');
    expect($metaTags)->toContain('property="og:title"');
    expect($metaTags)->toContain('property="og:description"');
    expect($metaTags)->toContain('property="og:image"');
    expect($metaTags)->toContain('name="twitter:card"');
    expect($metaTags)->toContain('Laravel 11 완벽 가이드');
});

test('SEO_추천사항이_생성된다', function () {
    // Given: 부족한 SEO 데이터
    $poorSeoData = [
        'title' => '짧은제목', // 30자 미만
        'description' => '짧은설명', // 120자 미만
        'keywords' => '', // 키워드 없음
        'image' => '', // 이미지 없음
    ];
    
    // SEO 분석 데이터로 변환
    $analysisData = [
        'title' => $poorSeoData['title'],
        'description' => $poorSeoData['description'],
        'keywords' => $poorSeoData['keywords'],
    ];
    
    $this->actingAs($this->authorUser);
    
    // When: SEO 분석 수행
    $response = $this->postJson('/api/v1/seo/analyze', $analysisData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 추천사항이 포함됨
    $response->assertStatus(200);
    
    $recommendations = $response->json('data.recommendations');
    expect($recommendations)->toBeArray();
    expect(count($recommendations))->toBeGreaterThan(0);
    
    // 제목이 짧다는 추천사항 확인
    $titleRecommendation = collect($recommendations)->first(function ($rec) {
        return str_contains($rec, '제목');
    });
    expect($titleRecommendation)->not->toBeNull();
});

test('존재하지_않는_포스트의_SEO_조회시_404_반환', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    // When: 존재하지 않는 포스트의 SEO 조회
    $response = $this->getJson('/api/v1/seo/post/999', [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 404 오류 반환
    $response->assertStatus(404);
});

test('SEO_미리보기에서_존재하지_않는_슬러그_처리', function () {
    // Given: 존재하지 않는 슬러그
    $fakeSlug = 'non-existent-post';
    
    // When: 존재하지 않는 슬러그로 미리보기 요청
    $response = $this->getJson("/api/v1/seo/preview/{$fakeSlug}");
    
    // Then: 404 오류 반환
    $response->assertStatus(404);
    expect($response->json('message'))->toContain('찾을 수 없습니다');
});