<?php

use App\Models\User;
use App\Models\Tag;
use App\Models\Post;

/**
 * Tag API 테스트
 * 
 * 3단계 태그 관련 API 엔드포인트들의 기능을 검증합니다.
 * - 태그 목록 조회
 * - 태그 상세 조회
 * - 태그 클라우드 조회
 * - 태그별 포스트 조회
 * - 태그 생성/수정/삭제 (관리자 권한)
 */

test('태그_목록_조회_성공', function () {
    // Given: 테스트용 태그 데이터 준비
    Tag::factory()->count(8)->create();

    // When: 태그 목록 API 호출
    $response = $this->getJson('/api/v1/tags');

    // Then: 성공 응답 및 데이터 구조 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'color',
                    'icon',
                    'is_featured',
                    'posts_count',
                    'urls' => [
                        'self',
                        'posts'
                    ],
                    'meta' => [
                        'usage_count',
                        'last_used_at'
                    ],
                    'created_at',
                    'updated_at'
                ]
            ],
            'meta',
            'message'
        ]);
    
    expect($response['data'])->toHaveCount(8);
});

test('태그_클라우드_조회_성공', function () {
    // Given: 포스트 수가 다른 태그들 생성
    $popularTag = Tag::factory()->create(['name' => '인기태그']);
    $regularTag = Tag::factory()->create(['name' => '일반태그']);
    
    // 포스트와 태그 연결 (인기태그에 더 많은 포스트)
    $posts1 = Post::factory()->count(5)->create();
    $posts2 = Post::factory()->count(2)->create();
    
    foreach ($posts1 as $post) {
        $post->tags()->attach($popularTag->id);
    }
    foreach ($posts2 as $post) {
        $post->tags()->attach($regularTag->id);
    }

    // When: 태그 클라우드 API 호출
    $response = $this->getJson('/api/v1/tags/cloud');

    // Then: 성공 응답 및 가중치가 포함된 데이터 구조 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'posts_count',
                    'weight',
                    'size_class',
                    'color'
                ]
            ],
            'message'
        ]);
});

test('태그_상세_조회_성공', function () {
    // Given: 테스트용 태그 생성
    $tag = Tag::factory()->create([
        'name' => '테스트태그',
        'slug' => 'test-tag',
        'description' => '테스트용 태그입니다.',
        'color' => '#FF5733',
        'is_featured' => true
    ]);

    // When: 태그 상세 API 호출
    $response = $this->getJson("/api/v1/tags/{$tag->slug}");

    // Then: 성공 응답 및 상세 데이터 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $tag->id,
                'name' => '테스트태그',
                'slug' => 'test-tag',
                'description' => '테스트용 태그입니다.',
                'color' => '#FF5733',
                'is_featured' => true
            ]
        ]);
});

test('존재하지_않는_태그_조회시_404_반환', function () {
    // When: 존재하지 않는 태그 조회
    $response = $this->getJson('/api/v1/tags/non-existent-tag');

    // Then: 404 에러 응답 확인
    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND'
            ]
        ]);
});

test('태그별_포스트_목록_조회_성공', function () {
    // Given: 태그와 포스트 데이터 준비
    $tag = Tag::factory()->create();
    $posts = Post::factory()->count(4)->create([
        'status' => 'published'
    ]);
    
    // 태그와 포스트 연결
    foreach ($posts as $post) {
        $post->tags()->attach($tag->id);
    }

    // When: 태그별 포스트 목록 API 호출
    $response = $this->getJson("/api/v1/tags/{$tag->slug}/posts");

    // Then: 성공 응답 및 포스트 데이터 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'slug',
                    'excerpt',
                    'status',
                    'author',
                    'tags',
                    'created_at',
                    'updated_at'
                ]
            ],
            'meta',
            'message'
        ]);
    
    expect($response['data'])->toHaveCount(4);
});

test('관리자가_태그_생성_성공', function () {
    // Given: 관리자 사용자 생성 및 로그인
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');

    $tagData = [
        'name' => '새로운태그',
        'description' => '새로 생성된 태그입니다.',
        'color' => '#00FF00',
        'icon' => 'fas fa-tag',
        'is_featured' => true
    ];

    // When: 태그 생성 API 호출
    $response = $this->postJson('/api/v1/tags', $tagData);

    // Then: 생성 성공 응답 및 데이터베이스 확인
    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'name' => '새로운태그',
                'description' => '새로 생성된 태그입니다.',
                'color' => '#00FF00',
                'icon' => 'fas fa-tag',
                'is_featured' => true
            ]
        ]);

    $this->assertDatabaseHas('tags', [
        'name' => '새로운태그',
        'description' => '새로 생성된 태그입니다.',
        'color' => '#00FF00'
    ]);
});

test('일반_사용자가_태그_생성시_권한_없음_에러', function () {
    // Given: 일반 사용자 생성 및 로그인
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user, 'api');

    $tagData = [
        'name' => '새로운태그',
        'description' => '새로 생성된 태그입니다.'
    ];

    // When: 태그 생성 API 호출
    $response = $this->postJson('/api/v1/tags', $tagData);

    // Then: 권한 없음 에러 응답 확인
    $response->assertStatus(403);
});

test('관리자가_태그_수정_성공', function () {
    // Given: 관리자 사용자 및 태그 생성
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');
    
    $tag = Tag::factory()->create([
        'name' => '원래태그',
        'description' => '원래 설명',
        'color' => '#FF0000'
    ]);

    $updateData = [
        'name' => '수정된태그',
        'description' => '수정된 설명',
        'color' => '#0000FF',
        'is_featured' => true
    ];

    // When: 태그 수정 API 호출
    $response = $this->putJson("/api/v1/tags/{$tag->id}", $updateData);

    // Then: 수정 성공 응답 및 데이터베이스 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'name' => '수정된태그',
                'description' => '수정된 설명',
                'color' => '#0000FF',
                'is_featured' => true
            ]
        ]);

    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'name' => '수정된태그',
        'description' => '수정된 설명',
        'color' => '#0000FF'
    ]);
});

test('관리자가_태그_삭제_성공', function () {
    // Given: 관리자 사용자 및 태그 생성
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');
    
    $tag = Tag::factory()->create();

    // When: 태그 삭제 API 호출
    $response = $this->deleteJson("/api/v1/tags/{$tag->id}");

    // Then: 삭제 성공 응답 및 데이터베이스 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true
        ]);

    $this->assertDatabaseMissing('tags', [
        'id' => $tag->id
    ]);
});

test('인기_태그_목록_조회_성공', function () {
    // Given: 다양한 사용빈도의 태그들 생성
    $tags = Tag::factory()->count(5)->create();
    $posts = Post::factory()->count(10)->create();
    
    // 태그별로 다른 수의 포스트 연결
    for ($i = 0; $i < count($tags); $i++) {
        $postCount = $i + 1;
        for ($j = 0; $j < $postCount; $j++) {
            $posts[$j]->tags()->attach($tags[$i]->id);
        }
    }

    // When: 태그 목록을 인기순으로 정렬하여 조회
    $response = $this->getJson('/api/v1/tags?sort_by=popular&limit=3');

    // Then: 인기순으로 정렬된 태그 목록 확인
    $response->assertStatus(200);
    expect($response['data'])->toHaveCount(3);
    
    // 첫 번째 태그가 가장 많은 포스트를 가져야 함
    $firstTag = $response['data'][0];
    $secondTag = $response['data'][1];
    expect($firstTag['posts_count'])->toBeGreaterThanOrEqual($secondTag['posts_count']);
});

test('태그_검색_기능_성공', function () {
    // Given: 검색 가능한 태그들 생성
    Tag::factory()->create(['name' => 'JavaScript']);
    Tag::factory()->create(['name' => 'Java']);
    Tag::factory()->create(['name' => 'Python']);
    Tag::factory()->create(['name' => 'Ruby']);

    // When: 'Ja'로 시작하는 태그 검색
    $response = $this->getJson('/api/v1/tags?search=Ja');

    // Then: JavaScript와 Java 태그만 반환되어야 함
    $response->assertStatus(200);
    expect($response['data'])->toHaveCount(2);
    
    $tagNames = collect($response['data'])->pluck('name')->toArray();
    expect($tagNames)->toContain('JavaScript');
    expect($tagNames)->toContain('Java');
    expect($tagNames)->not->toContain('Python');
});

test('태그_색상_유효성_검사', function () {
    // Given: 관리자 사용자 로그인
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');

    $invalidData = [
        'name' => '잘못된색상태그',
        'color' => 'invalid-color' // 잘못된 색상 형식
    ];

    // When: 잘못된 색상으로 태그 생성 시도
    $response = $this->postJson('/api/v1/tags', $invalidData);

    // Then: 유효성 검사 실패 응답 확인
    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR'
            ]
        ]);
});

test('태그_중복_이름_방지', function () {
    // Given: 관리자 사용자 및 기존 태그 생성
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');
    
    Tag::factory()->create(['name' => '기존태그']);

    $duplicateData = [
        'name' => '기존태그', // 중복된 이름
        'description' => '중복 테스트'
    ];

    // When: 중복된 이름으로 태그 생성 시도
    $response = $this->postJson('/api/v1/tags', $duplicateData);

    // Then: 중복 에러 응답 확인
    $response->assertStatus(409)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'DUPLICATE_RESOURCE'
            ]
        ]);
});