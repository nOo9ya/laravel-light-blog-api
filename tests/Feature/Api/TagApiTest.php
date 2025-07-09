<?php

/**
 * 테스트 목적: 태그 시스템 API 테스트
 * 테스트 시나리오: 태그 CRUD, 태그 클라우드, 인기 태그, 가중치 계산
 * 기대 결과: 태그 시스템이 정상 작동
 * 관련 비즈니스 규칙: 관리자만 수정 가능, 태그 클라우드 가중치, 인기도 기반 정렬
 */

use App\Models\User;
use App\Models\Tag;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Given: 테스트용 사용자 생성
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->authorUser = User::factory()->create(['role' => 'author']);
    $this->normalUser = User::factory()->create(['role' => 'user']);
    
    // JWT 토큰 생성
    $this->adminToken = auth()->login($this->adminUser);
    $this->authorToken = auth()->login($this->authorUser);
    $this->userToken = auth()->login($this->normalUser);
});

test('모든_사용자가_태그_목록을_조회할_수_있다', function () {
    // Given: 태그들과 연결된 포스트 생성
    $tags = Tag::factory()->count(5)->create();
    
    // 각 태그에 다른 수의 포스트 연결
    foreach ($tags as $index => $tag) {
        $posts = Post::factory()->count($index + 1)->create([
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        
        $tag->posts()->attach($posts);
    }
    
    // When: 태그 목록 조회 (인증 없음)
    $response = $this->getJson('/api/v1/tags');
    
    // Then: 태그 목록이 반환됨
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'posts_count',
                    'created_at'
                ]
            ]
        ]);
    
    expect($response->json('data'))->toHaveCount(5);
    
    // 포스트 수가 많은 태그가 먼저 나오는지 확인
    $tagData = $response->json('data');
    expect($tagData[0]['posts_count'])->toBeGreaterThanOrEqual($tagData[1]['posts_count']);
});

test('태그_클라우드를_조회할_수_있다', function () {
    // Given: 인기도가 다른 태그들 생성
    $popularTag = Tag::factory()->create(['name' => '인기 태그']);
    $normalTag = Tag::factory()->create(['name' => '일반 태그']);
    $unpopularTag = Tag::factory()->create(['name' => '비인기 태그']);
    
    // 포스트 연결 (인기도 차이)
    $popularPosts = Post::factory()->count(10)->create(['status' => 'published']);
    $normalPosts = Post::factory()->count(5)->create(['status' => 'published']);
    $unpopularPosts = Post::factory()->count(1)->create(['status' => 'published']);
    
    $popularTag->posts()->attach($popularPosts);
    $normalTag->posts()->attach($normalPosts);
    $unpopularTag->posts()->attach($unpopularPosts);
    
    // When: 태그 클라우드 조회
    $response = $this->getJson('/api/v1/tags/cloud?limit=10');
    
    // Then: 가중치와 함께 태그 클라우드 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'tags' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'posts_count',
                        'weight',
                        'size_class'
                    ]
                ],
                'max_count',
                'min_count'
            ]
        ]);
    
    $cloudData = $response->json('data');
    
    // 인기 태그가 가장 높은 가중치를 가지는지 확인
    $popularTagData = collect($cloudData['tags'])->first(fn($tag) => $tag['name'] === '인기 태그');
    $normalTagData = collect($cloudData['tags'])->first(fn($tag) => $tag['name'] === '일반 태그');
    
    expect($popularTagData['weight'])->toBeGreaterThan($normalTagData['weight']);
    expect($popularTagData['size_class'])->toBe('xl');
});

test('관리자가_태그를_생성할_수_있다', function () {
    // Given: 관리자로 인증
    $this->actingAs($this->adminUser);
    
    $tagData = [
        'name' => '새로운 태그',
        'description' => '태그 설명입니다.',
        'color' => '#FF5733',
        'meta_title' => 'SEO 제목',
        'meta_description' => 'SEO 설명',
    ];
    
    // When: 태그 생성 API 호출
    $response = $this->postJson('/api/v1/tags', $tagData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 태그가 성공적으로 생성됨
    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'slug',
                'description',
                'color',
                'meta_title',
                'meta_description',
                'created_at'
            ]
        ]);
    
    expect($response->json('data.name'))->toBe('새로운 태그');
    expect($response->json('data.slug'))->toBe('새로운-태그');
    expect($response->json('data.color'))->toBe('#FF5733');
    
    // 데이터베이스 확인
    $this->assertDatabaseHas('tags', [
        'name' => '새로운 태그',
        'slug' => '새로운-태그',
        'description' => '태그 설명입니다.',
        'color' => '#FF5733',
    ]);
});

test('작성자는_태그를_생성할_수_없다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $tagData = [
        'name' => '작성자 태그',
        'description' => '작성자가 만든 태그',
    ];
    
    // When: 태그 생성 시도
    $response = $this->postJson('/api/v1/tags', $tagData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 권한 부족으로 실패
    $response->assertStatus(403);
});

test('태그_상세_조회가_가능하다', function () {
    // Given: 포스트가 연결된 태그
    $tag = Tag::factory()->create([
        'name' => 'Laravel',
        'slug' => 'laravel',
        'description' => 'Laravel 프레임워크 관련 태그',
        'color' => '#FF2D20',
    ]);
    
    // 태그에 포스트 연결
    $posts = Post::factory()->count(3)->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    
    $tag->posts()->attach($posts);
    
    // When: 태그 상세 조회
    $response = $this->getJson("/api/v1/tags/{$tag->slug}");
    
    // Then: 태그 상세 정보와 포스트 목록 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'tag' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'color',
                    'posts_count',
                    'meta_title',
                    'meta_description'
                ],
                'posts' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'slug',
                            'excerpt',
                            'published_at',
                            'category',
                            'user'
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'total',
                        'per_page'
                    ]
                ]
            ]
        ]);
    
    expect($response->json('data.tag.name'))->toBe('Laravel');
    expect($response->json('data.tag.posts_count'))->toBe(3);
    expect($response->json('data.posts.data'))->toHaveCount(3);
});

test('관리자가_태그를_수정할_수_있다', function () {
    // Given: 기존 태그
    $tag = Tag::factory()->create([
        'name' => '원본 태그',
        'slug' => 'original-tag',
        'description' => '원본 설명',
        'color' => '#000000',
    ]);
    
    $this->actingAs($this->adminUser);
    
    $updateData = [
        'name' => '수정된 태그',
        'description' => '수정된 설명',
        'color' => '#FF5733',
        'meta_title' => '수정된 SEO 제목',
    ];
    
    // When: 태그 수정 API 호출
    $response = $this->putJson("/api/v1/tags/{$tag->id}", $updateData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 태그가 성공적으로 수정됨
    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('수정된 태그');
    expect($response->json('data.description'))->toBe('수정된 설명');
    expect($response->json('data.color'))->toBe('#FF5733');
    
    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'name' => '수정된 태그',
        'description' => '수정된 설명',
        'color' => '#FF5733',
    ]);
});

test('태그를_삭제할_수_있다', function () {
    // Given: 포스트가 연결되지 않은 태그
    $tag = Tag::factory()->create([
        'name' => '삭제할 태그',
    ]);
    
    $this->actingAs($this->adminUser);
    
    // When: 태그 삭제 API 호출
    $response = $this->deleteJson("/api/v1/tags/{$tag->id}", [], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 태그가 삭제됨
    $response->assertStatus(200);
    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});

test('포스트가_연결된_태그는_삭제할_수_없다', function () {
    // Given: 포스트가 연결된 태그
    $tag = Tag::factory()->create(['name' => '연결된 태그']);
    
    $post = Post::factory()->create(['status' => 'published']);
    $tag->posts()->attach($post);
    
    $this->actingAs($this->adminUser);
    
    // When: 포스트가 연결된 태그 삭제 시도
    $response = $this->deleteJson("/api/v1/tags/{$tag->id}", [], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 삭제 실패 (포스트 연결로 인한)
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('포스트가 연결');
});

test('인기_태그_목록을_조회할_수_있다', function () {
    // Given: 다양한 인기도의 태그들
    $tags = Tag::factory()->count(10)->create();
    
    foreach ($tags as $index => $tag) {
        $postCount = rand(1, 20);
        $posts = Post::factory()->count($postCount)->create([
            'status' => 'published',
            'published_at' => now()->subDays(rand(1, 30)),
        ]);
        $tag->posts()->attach($posts);
    }
    
    // When: 인기 태그 조회
    $response = $this->getJson('/api/v1/tags/popular?limit=5');
    
    // Then: 인기순으로 정렬된 태그 목록 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'posts_count',
                    'popularity_score'
                ]
            ]
        ]);
    
    expect($response->json('data'))->toHaveCount(5);
    
    // 인기도순으로 정렬되었는지 확인
    $popularTags = $response->json('data');
    for ($i = 0; $i < count($popularTags) - 1; $i++) {
        expect($popularTags[$i]['posts_count'])->toBeGreaterThanOrEqual($popularTags[$i + 1]['posts_count']);
    }
});

test('태그_검색이_가능하다', function () {
    // Given: 다양한 이름의 태그들
    Tag::factory()->create(['name' => 'Laravel']);
    Tag::factory()->create(['name' => 'PHP']);
    Tag::factory()->create(['name' => 'JavaScript']);
    Tag::factory()->create(['name' => 'Python']);
    
    // When: 'La'로 시작하는 태그 검색
    $response = $this->getJson('/api/v1/tags/search?q=La');
    
    // Then: 검색 결과 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'posts_count'
                ]
            ]
        ]);
    
    $searchResults = $response->json('data');
    expect($searchResults)->toHaveCount(1);
    expect($searchResults[0]['name'])->toBe('Laravel');
});

test('태그_자동완성이_작동한다', function () {
    // Given: 유사한 이름의 태그들
    Tag::factory()->create(['name' => 'Laravel']);
    Tag::factory()->create(['name' => 'Laravel 11']);
    Tag::factory()->create(['name' => 'Laravel API']);
    Tag::factory()->create(['name' => 'PHP']);
    
    // When: 자동완성 검색
    $response = $this->getJson('/api/v1/tags/autocomplete?q=Laravel&limit=5');
    
    // Then: 자동완성 제안 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'suggestions' => [
                    '*' => [
                        'id',
                        'name',
                        'slug'
                    ]
                ]
            ]
        ]);
    
    $suggestions = $response->json('data.suggestions');
    expect($suggestions)->toHaveCount(3);
    
    // Laravel로 시작하는 태그들만 반환되는지 확인
    foreach ($suggestions as $suggestion) {
        expect($suggestion['name'])->toContain('Laravel');
    }
});

test('태그_슬러그가_자동_생성된다', function () {
    // Given: 관리자로 인증
    $this->actingAs($this->adminUser);
    
    $tagData = [
        'name' => '한글 태그 이름',
        'description' => '한글로 된 태그입니다.',
    ];
    
    // When: 한글 이름으로 태그 생성
    $response = $this->postJson('/api/v1/tags', $tagData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 한글 슬러그가 자동 생성됨
    $response->assertStatus(201);
    expect($response->json('data.slug'))->toBe('한글-태그-이름');
});

test('중복된_태그_슬러그는_자동으로_숫자가_추가된다', function () {
    // Given: 기존 태그
    Tag::factory()->create([
        'name' => '테스트 태그',
        'slug' => '테스트-태그',
    ]);
    
    $this->actingAs($this->adminUser);
    
    // When: 같은 이름의 태그 생성
    $response = $this->postJson('/api/v1/tags', [
        'name' => '테스트 태그',
        'description' => '중복 이름 태그',
    ], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 슬러그에 숫자가 자동 추가됨
    $response->assertStatus(201);
    expect($response->json('data.slug'))->toBe('테스트-태그-2');
});

test('태그_유효성_검사가_작동한다', function () {
    // Given: 관리자로 인증
    $this->actingAs($this->adminUser);
    
    $invalidData = [
        'name' => '', // 이름 누락
        'color' => 'invalid-color', // 잘못된 색상 형식
    ];
    
    // When: 잘못된 데이터로 태그 생성 시도
    $response = $this->postJson('/api/v1/tags', $invalidData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 유효성 검사 오류 반환
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'color']);
});

test('관련_태그를_조회할_수_있다', function () {
    // Given: 공통 포스트를 가진 태그들
    $mainTag = Tag::factory()->create(['name' => '메인 태그']);
    $relatedTag1 = Tag::factory()->create(['name' => '관련 태그 1']);
    $relatedTag2 = Tag::factory()->create(['name' => '관련 태그 2']);
    $unrelatedTag = Tag::factory()->create(['name' => '무관한 태그']);
    
    // 공통 포스트 생성
    $commonPosts = Post::factory()->count(3)->create(['status' => 'published']);
    
    $mainTag->posts()->attach($commonPosts);
    $relatedTag1->posts()->attach($commonPosts->take(2));
    $relatedTag2->posts()->attach($commonPosts->take(1));
    
    // 무관한 태그는 다른 포스트에 연결
    $otherPosts = Post::factory()->count(2)->create(['status' => 'published']);
    $unrelatedTag->posts()->attach($otherPosts);
    
    // When: 관련 태그 조회
    $response = $this->getJson("/api/v1/tags/{$mainTag->id}/related?limit=5");
    
    // Then: 관련 태그들이 반환됨
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'posts_count',
                    'common_posts_count'
                ]
            ]
        ]);
    
    $relatedTags = $response->json('data');
    
    // 관련성이 높은 순으로 정렬되었는지 확인
    expect($relatedTags[0]['common_posts_count'])->toBeGreaterThanOrEqual($relatedTags[1]['common_posts_count']);
    
    // 메인 태그는 포함되지 않음
    $mainTagInResults = collect($relatedTags)->contains('id', $mainTag->id);
    expect($mainTagInResults)->toBeFalse();
});

test('존재하지_않는_태그_조회시_404_반환', function () {
    // Given: 존재하지 않는 슬러그
    $fakeSlug = 'non-existent-tag';
    
    // When: 존재하지 않는 태그 조회
    $response = $this->getJson("/api/v1/tags/{$fakeSlug}");
    
    // Then: 404 오류 반환
    $response->assertStatus(404);
});