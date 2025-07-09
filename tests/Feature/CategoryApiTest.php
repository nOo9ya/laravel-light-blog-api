<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Post;

/**
 * Category API 테스트
 * 
 * 3단계 카테고리 관련 API 엔드포인트들의 기능을 검증합니다.
 * - 카테고리 목록 조회
 * - 카테고리 상세 조회
 * - 카테고리별 포스트 조회
 * - 카테고리 생성/수정/삭제 (관리자 권한)
 */

test('카테고리_목록_조회_성공', function () {
    // Given: 테스트용 카테고리 데이터 준비
    Category::factory()->count(5)->create([
        'is_active' => true
    ]);

    // When: 카테고리 목록 API 호출
    $response = $this->getJson('/api/v1/categories');

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
                    'is_active',
                    'sort_order',
                    'posts_count',
                    'urls' => [
                        'self',
                        'posts',
                        'children'
                    ],
                    'meta' => [
                        'seo_title',
                        'seo_description',
                        'breadcrumb_trail'
                    ],
                    'created_at',
                    'updated_at'
                ]
            ],
            'meta',
            'message'
        ]);
    
    expect($response['data'])->toHaveCount(5);
});

test('카테고리_상세_조회_성공', function () {
    // Given: 테스트용 카테고리 생성
    $category = Category::factory()->create([
        'name' => '테스트 카테고리',
        'slug' => 'test-category',
        'description' => '테스트용 카테고리입니다.',
        'is_active' => true
    ]);

    // When: 카테고리 상세 API 호출
    $response = $this->getJson("/api/v1/categories/{$category->slug}");

    // Then: 성공 응답 및 상세 데이터 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'name' => '테스트 카테고리',
                'slug' => 'test-category',
                'description' => '테스트용 카테고리입니다.',
                'is_active' => true
            ]
        ]);
});

test('존재하지_않는_카테고리_조회시_404_반환', function () {
    // When: 존재하지 않는 카테고리 조회
    $response = $this->getJson('/api/v1/categories/non-existent-category');

    // Then: 404 에러 응답 확인
    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND'
            ]
        ]);
});

test('카테고리별_포스트_목록_조회_성공', function () {
    // Given: 카테고리와 포스트 데이터 준비
    $category = Category::factory()->create();
    $posts = Post::factory()->count(3)->create([
        'category_id' => $category->id,
        'status' => 'published'
    ]);

    // When: 카테고리별 포스트 목록 API 호출
    $response = $this->getJson("/api/v1/categories/{$category->slug}/posts");

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
                    'category',
                    'author',
                    'created_at',
                    'updated_at'
                ]
            ],
            'meta',
            'message'
        ]);
    
    expect($response['data'])->toHaveCount(3);
});

test('관리자가_카테고리_생성_성공', function () {
    // Given: 관리자 사용자 생성 및 로그인
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');

    $categoryData = [
        'name' => '새로운 카테고리',
        'description' => '새로 생성된 카테고리입니다.',
        'type' => 'both',
        'is_active' => true
    ];

    // When: 카테고리 생성 API 호출
    $response = $this->postJson('/api/v1/categories', $categoryData);

    // Then: 생성 성공 응답 및 데이터베이스 확인
    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'name' => '새로운 카테고리',
                'description' => '새로 생성된 카테고리입니다.',
                'type' => 'both',
                'is_active' => true
            ]
        ]);

    $this->assertDatabaseHas('categories', [
        'name' => '새로운 카테고리',
        'description' => '새로 생성된 카테고리입니다.'
    ]);
});

test('일반_사용자가_카테고리_생성시_권한_없음_에러', function () {
    // Given: 일반 사용자 생성 및 로그인
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user, 'api');

    $categoryData = [
        'name' => '새로운 카테고리',
        'description' => '새로 생성된 카테고리입니다.'
    ];

    // When: 카테고리 생성 API 호출
    $response = $this->postJson('/api/v1/categories', $categoryData);

    // Then: 권한 없음 에러 응답 확인
    $response->assertStatus(403);
});

test('관리자가_카테고리_수정_성공', function () {
    // Given: 관리자 사용자 및 카테고리 생성
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');
    
    $category = Category::factory()->create([
        'name' => '원래 카테고리',
        'description' => '원래 설명'
    ]);

    $updateData = [
        'name' => '수정된 카테고리',
        'description' => '수정된 설명',
        'is_active' => false
    ];

    // When: 카테고리 수정 API 호출
    $response = $this->putJson("/api/v1/categories/{$category->id}", $updateData);

    // Then: 수정 성공 응답 및 데이터베이스 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'name' => '수정된 카테고리',
                'description' => '수정된 설명',
                'is_active' => false
            ]
        ]);

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => '수정된 카테고리',
        'description' => '수정된 설명',
        'is_active' => false
    ]);
});

test('관리자가_카테고리_삭제_성공', function () {
    // Given: 관리자 사용자 및 카테고리 생성
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');
    
    $category = Category::factory()->create();

    // When: 카테고리 삭제 API 호출
    $response = $this->deleteJson("/api/v1/categories/{$category->id}");

    // Then: 삭제 성공 응답 및 데이터베이스 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true
        ]);

    $this->assertDatabaseMissing('categories', [
        'id' => $category->id
    ]);
});

test('카테고리_계층구조_조회_성공', function () {
    // Given: 부모-자식 카테고리 구조 생성
    $parentCategory = Category::factory()->create([
        'name' => '부모 카테고리',
        'parent_id' => null
    ]);
    
    $childCategory = Category::factory()->create([
        'name' => '자식 카테고리',
        'parent_id' => $parentCategory->id
    ]);

    // When: 자식 카테고리 조회
    $response = $this->getJson("/api/v1/categories/{$childCategory->slug}");

    // Then: 부모 정보가 포함된 응답 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'parent_id',
                'parent',
                'children'
            ]
        ]);
    
    expect($response['data']['parent_id'])->toBe($parentCategory->id);
});

test('카테고리_자식_목록_조회_성공', function () {
    // Given: 부모-자식 카테고리 구조 생성
    $parentCategory = Category::factory()->create();
    $childCategories = Category::factory()->count(3)->create([
        'parent_id' => $parentCategory->id
    ]);

    // When: 자식 카테고리 목록 조회
    $response = $this->getJson("/api/v1/categories/{$parentCategory->slug}/children");

    // Then: 자식 카테고리들이 포함된 응답 확인
    $response->assertStatus(200);
    expect($response['data'])->toHaveCount(3);
});

test('카테고리_유효성_검사_실패', function () {
    // Given: 관리자 사용자 로그인
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');

    $invalidData = [
        'name' => '', // 필수값 누락
        'type' => 'invalid_type' // 잘못된 타입
    ];

    // When: 잘못된 데이터로 카테고리 생성 시도
    $response = $this->postJson('/api/v1/categories', $invalidData);

    // Then: 유효성 검사 실패 응답 확인
    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR'
            ]
        ]);
});