<?php

/**
 * 테스트 목적: 계층형 카테고리 시스템 API 테스트
 * 테스트 시나리오: 카테고리 CRUD, 계층구조 관리, 슬러그 생성
 * 기대 결과: 계층형 카테고리 시스템이 정상 작동
 * 관련 비즈니스 규칙: 관리자만 수정 가능, 무제한 depth, 슬러그 자동 생성
 */

use App\Models\User;
use App\Models\Category;
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

test('모든_사용자가_카테고리_목록을_조회할_수_있다', function () {
    // Given: 계층형 카테고리 구조 생성
    $parentCategory = Category::factory()->create([
        'name' => '프로그래밍',
        'slug' => 'programming',
        'parent_id' => null,
        'order' => 1,
    ]);
    
    $childCategory = Category::factory()->create([
        'name' => 'Laravel',
        'slug' => 'laravel', 
        'parent_id' => $parentCategory->id,
        'order' => 1,
    ]);
    
    $grandChildCategory = Category::factory()->create([
        'name' => 'Laravel 11',
        'slug' => 'laravel-11',
        'parent_id' => $childCategory->id,
        'order' => 1,
    ]);
    
    // When: 카테고리 목록 조회 (인증 없음)
    $response = $this->getJson('/api/v1/categories');
    
    // Then: 계층구조 카테고리 목록이 반환됨
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
                    'parent_id',
                    'order',
                    'posts_count',
                    'children'
                ]
            ]
        ]);
    
    // 부모 카테고리에 자식이 포함되어 있는지 확인
    $categories = $response->json('data');
    $parentData = collect($categories)->first(fn($cat) => $cat['id'] === $parentCategory->id);
    
    expect($parentData)->not->toBeNull();
    expect($parentData['children'])->toHaveCount(1);
    expect($parentData['children'][0]['name'])->toBe('Laravel');
    expect($parentData['children'][0]['children'])->toHaveCount(1);
    expect($parentData['children'][0]['children'][0]['name'])->toBe('Laravel 11');
});

test('관리자가_카테고리를_생성할_수_있다', function () {
    // Given: 관리자로 인증
    $this->actingAs($this->adminUser);
    
    $categoryData = [
        'name' => '새로운 카테고리',
        'description' => '카테고리 설명입니다.',
        'parent_id' => null,
        'order' => 1,
        'meta_title' => 'SEO 제목',
        'meta_description' => 'SEO 설명',
    ];
    
    // When: 카테고리 생성 API 호출
    $response = $this->postJson('/api/v1/categories', $categoryData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 카테고리가 성공적으로 생성됨
    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'slug',
                'description',
                'parent_id',
                'order',
                'meta_title',
                'meta_description',
                'created_at'
            ]
        ]);
    
    expect($response->json('data.name'))->toBe('새로운 카테고리');
    expect($response->json('data.slug'))->toBe('새로운-카테고리');
    
    // 데이터베이스 확인
    $this->assertDatabaseHas('categories', [
        'name' => '새로운 카테고리',
        'slug' => '새로운-카테고리',
        'description' => '카테고리 설명입니다.',
    ]);
});

test('하위_카테고리를_생성할_수_있다', function () {
    // Given: 부모 카테고리 생성
    $parentCategory = Category::factory()->create([
        'name' => '부모 카테고리',
        'slug' => 'parent-category',
    ]);
    
    $this->actingAs($this->adminUser);
    
    $childCategoryData = [
        'name' => '자식 카테고리',
        'description' => '자식 카테고리입니다.',
        'parent_id' => $parentCategory->id,
        'order' => 1,
    ];
    
    // When: 하위 카테고리 생성
    $response = $this->postJson('/api/v1/categories', $childCategoryData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 하위 카테고리가 성공적으로 생성됨
    $response->assertStatus(201);
    expect($response->json('data.parent_id'))->toBe($parentCategory->id);
    expect($response->json('data.name'))->toBe('자식 카테고리');
    
    // 부모-자식 관계 확인
    $this->assertDatabaseHas('categories', [
        'name' => '자식 카테고리',
        'parent_id' => $parentCategory->id,
    ]);
});

test('작성자는_카테고리를_생성할_수_없다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $categoryData = [
        'name' => '작성자 카테고리',
        'description' => '작성자가 만든 카테고리',
    ];
    
    // When: 카테고리 생성 시도
    $response = $this->postJson('/api/v1/categories', $categoryData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 권한 부족으로 실패
    $response->assertStatus(403);
});

test('카테고리_상세_조회가_가능하다', function () {
    // Given: 포스트가 있는 카테고리
    $category = Category::factory()->create([
        'name' => 'Laravel 튜토리얼',
        'slug' => 'laravel-tutorial',
        'description' => 'Laravel 관련 튜토리얼입니다.',
    ]);
    
    // 카테고리에 포스트 추가
    Post::factory()->count(3)->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    
    // When: 카테고리 상세 조회
    $response = $this->getJson("/api/v1/categories/{$category->slug}");
    
    // Then: 카테고리 상세 정보와 포스트 목록 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'category' => [
                    'id',
                    'name',
                    'slug',
                    'description',
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
    
    expect($response->json('data.category.name'))->toBe('Laravel 튜토리얼');
    expect($response->json('data.category.posts_count'))->toBe(3);
    expect($response->json('data.posts.data'))->toHaveCount(3);
});

test('관리자가_카테고리를_수정할_수_있다', function () {
    // Given: 기존 카테고리
    $category = Category::factory()->create([
        'name' => '원본 카테고리',
        'slug' => 'original-category',
        'description' => '원본 설명',
    ]);
    
    $this->actingAs($this->adminUser);
    
    $updateData = [
        'name' => '수정된 카테고리',
        'description' => '수정된 설명',
        'order' => 2,
        'meta_title' => '수정된 SEO 제목',
    ];
    
    // When: 카테고리 수정 API 호출
    $response = $this->putJson("/api/v1/categories/{$category->id}", $updateData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 카테고리가 성공적으로 수정됨
    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('수정된 카테고리');
    expect($response->json('data.description'))->toBe('수정된 설명');
    
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => '수정된 카테고리',
        'description' => '수정된 설명',
    ]);
});

test('카테고리_순서를_변경할_수_있다', function () {
    // Given: 같은 부모를 가진 카테고리들
    $parentCategory = Category::factory()->create();
    
    $category1 = Category::factory()->create([
        'parent_id' => $parentCategory->id,
        'order' => 1,
        'name' => '카테고리 1',
    ]);
    
    $category2 = Category::factory()->create([
        'parent_id' => $parentCategory->id,
        'order' => 2,
        'name' => '카테고리 2',
    ]);
    
    $this->actingAs($this->adminUser);
    
    // When: 카테고리 순서 변경
    $response = $this->postJson('/api/v1/categories/reorder', [
        'orders' => [
            ['id' => $category1->id, 'order' => 2],
            ['id' => $category2->id, 'order' => 1],
        ]
    ], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 순서가 성공적으로 변경됨
    $response->assertStatus(200);
    
    $this->assertDatabaseHas('categories', [
        'id' => $category1->id,
        'order' => 2,
    ]);
    
    $this->assertDatabaseHas('categories', [
        'id' => $category2->id,
        'order' => 1,
    ]);
});

test('카테고리를_삭제할_수_있다', function () {
    // Given: 포스트가 없는 카테고리
    $category = Category::factory()->create([
        'name' => '삭제할 카테고리',
    ]);
    
    $this->actingAs($this->adminUser);
    
    // When: 카테고리 삭제 API 호출
    $response = $this->deleteJson("/api/v1/categories/{$category->id}", [], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 카테고리가 삭제됨
    $response->assertStatus(200);
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('포스트가_있는_카테고리는_삭제할_수_없다', function () {
    // Given: 포스트가 있는 카테고리
    $category = Category::factory()->create();
    
    Post::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
    ]);
    
    $this->actingAs($this->adminUser);
    
    // When: 포스트가 있는 카테고리 삭제 시도
    $response = $this->deleteJson("/api/v1/categories/{$category->id}", [], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 삭제 실패 (포스트 존재로 인한)
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('포스트가 존재');
});

test('하위_카테고리가_있는_경우_삭제할_수_없다', function () {
    // Given: 하위 카테고리가 있는 부모 카테고리
    $parentCategory = Category::factory()->create(['name' => '부모 카테고리']);
    $childCategory = Category::factory()->create([
        'parent_id' => $parentCategory->id,
        'name' => '자식 카테고리',
    ]);
    
    $this->actingAs($this->adminUser);
    
    // When: 하위 카테고리가 있는 카테고리 삭제 시도
    $response = $this->deleteJson("/api/v1/categories/{$parentCategory->id}", [], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 삭제 실패 (하위 카테고리 존재)
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('하위 카테고리가 존재');
});

test('카테고리_슬러그가_자동_생성된다', function () {
    // Given: 관리자로 인증
    $this->actingAs($this->adminUser);
    
    $categoryData = [
        'name' => '한글 카테고리 이름',
        'description' => '한글로 된 카테고리입니다.',
    ];
    
    // When: 한글 이름으로 카테고리 생성
    $response = $this->postJson('/api/v1/categories', $categoryData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 한글 슬러그가 자동 생성됨
    $response->assertStatus(201);
    expect($response->json('data.slug'))->toBe('한글-카테고리-이름');
});

test('중복된_슬러그는_자동으로_숫자가_추가된다', function () {
    // Given: 기존 카테고리
    Category::factory()->create([
        'name' => '테스트 카테고리',
        'slug' => '테스트-카테고리',
    ]);
    
    $this->actingAs($this->adminUser);
    
    // When: 같은 이름의 카테고리 생성
    $response = $this->postJson('/api/v1/categories', [
        'name' => '테스트 카테고리',
        'description' => '중복 이름 카테고리',
    ], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 슬러그에 숫자가 자동 추가됨
    $response->assertStatus(201);
    expect($response->json('data.slug'))->toBe('테스트-카테고리-2');
});

test('카테고리_유효성_검사가_작동한다', function () {
    // Given: 관리자로 인증
    $this->actingAs($this->adminUser);
    
    $invalidData = [
        'name' => '', // 이름 누락
        'parent_id' => 999, // 존재하지 않는 부모
    ];
    
    // When: 잘못된 데이터로 카테고리 생성 시도
    $response = $this->postJson('/api/v1/categories', $invalidData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 유효성 검사 오류 반환
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'parent_id']);
});

test('존재하지_않는_카테고리_조회시_404_반환', function () {
    // Given: 존재하지 않는 슬러그
    $fakeSlug = 'non-existent-category';
    
    // When: 존재하지 않는 카테고리 조회
    $response = $this->getJson("/api/v1/categories/{$fakeSlug}");
    
    // Then: 404 오류 반환
    $response->assertStatus(404);
});