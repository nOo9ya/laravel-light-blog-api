<?php

/**
 * 테스트 목적: 검색 시스템 및 Analytics 기능 테스트
 * 테스트 시나리오: 통합 검색, 자동완성, 인기 검색어, 검색 통계
 * 기대 결과: 검색 기능과 분석 시스템이 정상 작동
 * 관련 비즈니스 규칙: 보안 검색어 필터링, 검색 로그 기록, 캐시 활용
 */

use App\Models\User;
use App\Models\Post;
use App\Models\Page;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Analytics;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Given: 테스트용 데이터 생성
    $this->category = Category::factory()->create(['name' => 'Laravel 튜토리얼']);
    $this->tags = Tag::factory()->count(3)->create([
        'name' => ['Laravel', 'PHP', 'API']
    ]);
    
    // 검색할 포스트들 생성
    $this->posts = Post::factory()->count(5)->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'category_id' => $this->category->id,
    ]);
    
    // 첫 번째 포스트에 특정 제목 설정
    $this->posts[0]->update([
        'title' => 'Laravel 11 완벽 가이드',
        'content' => 'Laravel 11의 새로운 기능들을 상세히 알아봅시다.',
        'summary' => 'Laravel 11 가이드'
    ]);
    
    // 두 번째 포스트에 다른 키워드 설정
    $this->posts[1]->update([
        'title' => 'PHP 8.3 신기능 소개',
        'content' => 'PHP 8.3에서 추가된 신기능들을 살펴봅시다.',
    ]);
    
    // 페이지 생성
    $this->pages = Page::factory()->count(2)->create([
        'status' => 'published',
        'title' => 'Laravel 문서',
        'content' => 'Laravel 프레임워크 문서입니다.'
    ]);
    
    // 태그에 포스트 연결
    $this->posts[0]->tags()->attach($this->tags);
    
    $this->searchService = app(SearchService::class);
});

test('통합_검색이_정상_작동한다', function () {
    // Given: 검색어 준비
    $searchQuery = 'Laravel';
    
    // When: 통합 검색 API 호출
    $response = $this->getJson("/api/v1/search?q={$searchQuery}&type=all&limit=10");
    
    // Then: 모든 타입의 검색 결과 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'posts',
                'pages', 
                'categories',
                'tags',
                'total',
                'query'
            ]
        ]);
    
    expect($response->json('data.query'))->toBe($searchQuery);
    expect($response->json('data.total'))->toBeGreaterThan(0);
    
    // 포스트 검색 결과 확인
    $posts = $response->json('data.posts');
    expect($posts)->toBeArray();
    expect(count($posts))->toBeGreaterThan(0);
    
    // Laravel이 포함된 결과인지 확인
    $foundLaravel = collect($posts)->contains(function ($post) {
        return str_contains($post['title'], 'Laravel') || str_contains($post['content'], 'Laravel');
    });
    expect($foundLaravel)->toBeTrue();
});

test('포스트만_검색할_수_있다', function () {
    // Given: 포스트 검색 요청
    $searchQuery = 'Laravel';
    
    // When: 포스트만 검색
    $response = $this->getJson("/api/v1/search/posts?q={$searchQuery}&per_page=5");
    
    // Then: 포스트 검색 결과만 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'excerpt',
                        'category',
                        'tags',
                        'user',
                        'published_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page'
                ]
            ]
        ]);
    
    // Laravel이 포함된 포스트 확인
    $posts = $response->json('data.data');
    $foundPost = collect($posts)->first(function ($post) {
        return str_contains($post['title'], 'Laravel');
    });
    expect($foundPost)->not->toBeNull();
    expect($foundPost['title'])->toBe('Laravel 11 완벽 가이드');
});

test('검색어_자동완성이_작동한다', function () {
    // Given: 자동완성 검색어
    $partialQuery = 'Lar';
    
    // When: 자동완성 API 호출
    $response = $this->getJson("/api/v1/search/autocomplete?q={$partialQuery}&limit=5");
    
    // Then: 자동완성 제안 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'suggestions'
            ]
        ]);
    
    $suggestions = $response->json('data.suggestions');
    expect($suggestions)->toBeArray();
    
    // Laravel로 시작하는 제안이 있는지 확인
    $hasLaravelSuggestion = collect($suggestions)->contains(function ($suggestion) {
        return str_starts_with($suggestion, 'Lar');
    });
    expect($hasLaravelSuggestion)->toBeTrue();
});

test('인기_검색어를_조회할_수_있다', function () {
    // Given: 검색 로그 데이터 생성
    Analytics::create([
        'event_type' => 'search',
        'event_data' => [
            'query' => 'Laravel',
            'type' => 'all',
            'results_count' => 5,
        ],
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(1),
    ]);
    
    Analytics::create([
        'event_type' => 'search',
        'event_data' => [
            'query' => 'PHP',
            'type' => 'posts',
            'results_count' => 3,
        ],
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(2),
    ]);
    
    // When: 인기 검색어 조회
    $response = $this->getJson('/api/v1/search/popular?limit=10&days=30');
    
    // Then: 인기 검색어 목록 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'popular_searches' => [
                    '*' => [
                        'query',
                        'count',
                        'avg_results'
                    ]
                ]
            ]
        ]);
    
    $popularSearches = $response->json('data.popular_searches');
    expect($popularSearches)->toBeArray();
    
    if (!empty($popularSearches)) {
        expect($popularSearches[0])->toHaveKeys(['query', 'count', 'avg_results']);
    }
});

test('관련_검색어를_제안할_수_있다', function () {
    // Given: 관련 검색 데이터 생성
    Analytics::create([
        'event_type' => 'search',
        'event_data' => [
            'query' => 'Laravel API',
            'type' => 'all',
            'results_count' => 4,
        ],
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(1),
    ]);
    
    Analytics::create([
        'event_type' => 'search',
        'event_data' => [
            'query' => 'Laravel 튜토리얼',
            'type' => 'posts',
            'results_count' => 6,
        ],
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(3),
    ]);
    
    // When: 관련 검색어 조회
    $response = $this->getJson('/api/v1/search/related?q=Laravel&limit=5');
    
    // Then: 관련 검색어 목록 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'related_searches'
            ]
        ]);
    
    $relatedSearches = $response->json('data.related_searches');
    expect($relatedSearches)->toBeArray();
});

test('검색_통계를_조회할_수_있다', function () {
    // Given: 검색 통계 데이터 생성
    Analytics::factory()->count(10)->create([
        'event_type' => 'search',
        'event_data' => [
            'query' => 'Laravel',
            'type' => 'all',
            'results_count' => rand(1, 10),
        ],
        'created_at' => now()->subDays(rand(1, 7)),
    ]);
    
    Analytics::factory()->count(5)->create([
        'event_type' => 'search',
        'event_data' => [
            'query' => 'PHP',
            'type' => 'posts',
            'results_count' => rand(1, 5),
        ],
        'created_at' => now()->subDays(rand(1, 7)),
    ]);
    
    // When: 검색 통계 조회
    $response = $this->getJson('/api/v1/search/suggestions?days=30');
    
    // Then: 검색 통계 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'total_searches',
                'unique_queries',
                'avg_results_per_search',
                'searches_per_day',
                'popular_searches'
            ]
        ]);
    
    $stats = $response->json('data');
    expect($stats['total_searches'])->toBeGreaterThan(0);
    expect($stats['unique_queries'])->toBeGreaterThan(0);
});

test('검색_로그가_자동_기록된다', function () {
    // Given: 검색 요청 준비
    $searchQuery = 'Laravel 테스트';
    
    // When: 검색 API 호출
    $response = $this->getJson("/api/v1/search?q={$searchQuery}&type=all");
    
    // Then: 검색이 성공하고 로그가 기록됨
    $response->assertStatus(200);
    
    // 검색 로그 확인
    $this->assertDatabaseHas('analytics', [
        'event_type' => 'search',
    ]);
    
    $searchLog = Analytics::where('event_type', 'search')
        ->whereJsonContains('event_data->query', $searchQuery)
        ->first();
        
    expect($searchLog)->not->toBeNull();
    expect($searchLog->event_data['query'])->toBe($searchQuery);
    expect($searchLog->event_data['type'])->toBe('all');
});

test('짧은_검색어는_거부된다', function () {
    // Given: 너무 짧은 검색어
    $shortQuery = 'a';
    
    // When: 짧은 검색어로 검색 시도
    $response = $this->getJson("/api/v1/search?q={$shortQuery}");
    
    // Then: 유효성 검사 오류 반환
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

test('위험한_검색어는_필터링된다', function () {
    // Given: SQL 인젝션 시도 검색어
    $dangerousQuery = "'; DROP TABLE posts; --";
    
    // When: 위험한 검색어로 검색 시도
    $response = $this->getJson("/api/v1/search?q=" . urlencode($dangerousQuery));
    
    // Then: 검색이 실행되지만 안전하게 처리됨
    $response->assertStatus(200);
    
    // 빈 결과 또는 안전한 결과만 반환
    $data = $response->json('data');
    expect($data['total'])->toBe(0);
});

test('SearchService_직접_테스트', function () {
    // Given: SearchService 인스턴스
    $searchService = app(SearchService::class);
    
    // When: 검색 서비스 직접 호출
    $results = $searchService->search('Laravel', 'posts', 5);
    
    // Then: 검색 결과 구조 확인
    expect($results)->toHaveKeys(['posts', 'pages', 'categories', 'tags', 'total', 'query']);
    expect($results['query'])->toBe('Laravel');
    expect($results['posts'])->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('자동완성_제안이_관련성_순으로_정렬된다', function () {
    // Given: 다양한 제목의 포스트들
    Post::factory()->create([
        'title' => 'Laravel API 개발',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    
    Post::factory()->create([
        'title' => 'Laravel 기초부터 고급까지',
        'status' => 'published', 
        'published_at' => now()->subDay(),
    ]);
    
    Post::factory()->create([
        'title' => '고급 Laravel 기술',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    
    // When: 자동완성 검색
    $suggestions = $this->searchService->getAutocompleteSuggestions('Laravel', 5);
    
    // Then: Laravel로 시작하는 제안이 먼저 나옴
    expect($suggestions)->toBeArray();
    
    if (!empty($suggestions)) {
        $firstSuggestion = $suggestions[0];
        expect(str_starts_with($firstSuggestion, 'Laravel'))->toBeTrue();
    }
});

test('인기_검색어_캐시가_작동한다', function () {
    // Given: 캐시 클리어
    Cache::forget('popular_searches_10_30');
    
    // 검색 로그 생성
    Analytics::create([
        'event_type' => 'search',
        'event_data' => [
            'query' => 'Laravel',
            'results_count' => 5,
        ],
        'created_at' => now()->subDay(),
    ]);
    
    // When: 첫 번째 인기 검색어 조회
    $firstCall = $this->searchService->getPopularSearches(10, 30);
    
    // 데이터 변경
    Analytics::create([
        'event_type' => 'search',
        'event_data' => [
            'query' => 'PHP',
            'results_count' => 3,
        ],
        'created_at' => now()->subDay(),
    ]);
    
    // 두 번째 호출
    $secondCall = $this->searchService->getPopularSearches(10, 30);
    
    // Then: 캐시된 결과가 반환됨 (변경사항 반영 안됨)
    expect($firstCall)->toBe($secondCall);
});

test('페이지네이션이_포함된_검색이_작동한다', function () {
    // Given: 많은 포스트 생성
    Post::factory()->count(15)->create([
        'title' => 'Laravel 관련 포스트',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    
    // When: 페이지네이션 검색
    $response = $this->getJson('/api/v1/search/posts?q=Laravel&per_page=5&page=1');
    
    // Then: 페이지네이션 정보 포함
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page'
                ]
            ]
        ]);
    
    $meta = $response->json('data.meta');
    expect($meta['per_page'])->toBe(5);
    expect($meta['current_page'])->toBe(1);
    expect($meta['total'])->toBeGreaterThan(5);
});