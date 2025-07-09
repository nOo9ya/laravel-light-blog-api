<?php

/**
 * 테스트 목적: 포스트 CRUD API 및 고급 기능 테스트
 * 테스트 시나리오: 포스트 생성, 조회, 수정, 삭제, 발행/비공개, 슬러그 생성
 * 기대 결과: 모든 포스트 관련 API가 정상 작동
 * 관련 비즈니스 규칙: 작성자/관리자만 수정 가능, 발행된 포스트만 공개 조회
 */

use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Given: 테스트용 사용자 및 카테고리 생성
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->authorUser = User::factory()->create(['role' => 'author']);
    $this->normalUser = User::factory()->create(['role' => 'user']);
    
    $this->category = Category::factory()->create();
    $this->tags = Tag::factory()->count(3)->create();
    
    // JWT 토큰 생성
    $this->adminToken = auth()->login($this->adminUser);
    $this->authorToken = auth()->login($this->authorUser);
    $this->normalToken = auth()->login($this->normalUser);
});

test('관리자가_포스트를_생성할_수_있다', function () {
    // Given: 관리자 권한으로 인증
    $this->actingAs($this->adminUser);
    
    $postData = [
        'title' => '테스트 포스트 제목',
        'content' => '테스트 포스트 내용입니다.',
        'summary' => '테스트 요약',
        'category_id' => $this->category->id,
        'tag_ids' => $this->tags->pluck('id')->toArray(),
        'status' => 'published',
    ];
    
    // When: 포스트 생성 API 호출
    $response = $this->postJson('/api/v1/posts', $postData, [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 포스트가 성공적으로 생성됨
    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'title',
                'slug',
                'content',
                'summary',
                'status',
                'category',
                'tags',
                'user',
                'created_at'
            ]
        ]);
    
    expect($response->json('data.title'))->toBe('테스트 포스트 제목');
    expect($response->json('data.slug'))->toContain('테스트-포스트-제목');
    
    // 데이터베이스 확인
    $this->assertDatabaseHas('posts', [
        'title' => '테스트 포스트 제목',
        'user_id' => $this->adminUser->id,
        'category_id' => $this->category->id,
    ]);
});

test('작성자가_포스트를_생성할_수_있다', function () {
    // Given: 작성자 권한으로 인증
    $this->actingAs($this->authorUser);
    
    $postData = [
        'title' => '작성자 포스트',
        'content' => '작성자가 작성한 포스트입니다.',
        'category_id' => $this->category->id,
        'status' => 'draft',
    ];
    
    // When: 포스트 생성 API 호출
    $response = $this->postJson('/api/v1/posts', $postData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 포스트가 성공적으로 생성됨
    $response->assertStatus(201);
    expect($response->json('data.user.id'))->toBe($this->authorUser->id);
});

test('일반_사용자는_포스트를_생성할_수_없다', function () {
    // Given: 일반 사용자로 인증
    $this->actingAs($this->normalUser);
    
    $postData = [
        'title' => '테스트 포스트',
        'content' => '테스트 내용',
        'category_id' => $this->category->id,
    ];
    
    // When: 포스트 생성 API 호출
    $response = $this->postJson('/api/v1/posts', $postData, [
        'Authorization' => 'Bearer ' . $this->normalToken
    ]);
    
    // Then: 권한 부족으로 실패
    $response->assertStatus(403);
});

test('발행된_포스트_목록을_조회할_수_있다', function () {
    // Given: 발행된 포스트와 초안 포스트 생성
    $publishedPosts = Post::factory()->count(3)->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'category_id' => $this->category->id,
    ]);
    
    $draftPost = Post::factory()->create([
        'status' => 'draft',
        'category_id' => $this->category->id,
    ]);
    
    // When: 포스트 목록 조회
    $response = $this->getJson('/api/v1/posts');
    
    // Then: 발행된 포스트만 반환됨
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
                        'status',
                        'published_at',
                        'category',
                        'tags',
                        'user'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page'
                ]
            ]
        ]);
    
    expect($response->json('data.data'))->toHaveCount(3);
    
    // 초안은 포함되지 않음
    $returnedIds = collect($response->json('data.data'))->pluck('id');
    expect($returnedIds)->not->toContain($draftPost->id);
});

test('포스트_상세_조회가_가능하다', function () {
    // Given: 발행된 포스트 생성
    $post = Post::factory()->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'category_id' => $this->category->id,
        'user_id' => $this->authorUser->id,
    ]);
    
    $post->tags()->attach($this->tags);
    
    // When: 포스트 상세 조회
    $response = $this->getJson("/api/v1/posts/{$post->slug}");
    
    // Then: 포스트 상세 정보 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'title',
                'slug',
                'content',
                'summary',
                'status',
                'published_at',
                'views_count',
                'reading_time',
                'category',
                'tags',
                'user',
                'seo_meta',
                'created_at',
                'updated_at'
            ]
        ]);
    
    expect($response->json('data.id'))->toBe($post->id);
    expect($response->json('data.tags'))->toHaveCount(3);
});

test('작성자가_자신의_포스트를_수정할_수_있다', function () {
    // Given: 작성자가 작성한 포스트
    $post = Post::factory()->create([
        'user_id' => $this->authorUser->id,
        'category_id' => $this->category->id,
    ]);
    
    $this->actingAs($this->authorUser);
    
    $updateData = [
        'title' => '수정된 제목',
        'content' => '수정된 내용',
        'summary' => '수정된 요약',
    ];
    
    // When: 포스트 수정 API 호출
    $response = $this->putJson("/api/v1/posts/{$post->id}", $updateData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 포스트가 성공적으로 수정됨
    $response->assertStatus(200);
    expect($response->json('data.title'))->toBe('수정된 제목');
    
    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'title' => '수정된 제목',
        'content' => '수정된 내용',
    ]);
});

test('다른_사용자의_포스트는_수정할_수_없다', function () {
    // Given: 다른 사용자가 작성한 포스트
    $post = Post::factory()->create([
        'user_id' => $this->adminUser->id,
        'category_id' => $this->category->id,
    ]);
    
    $this->actingAs($this->authorUser);
    
    $updateData = [
        'title' => '수정된 제목',
    ];
    
    // When: 다른 사용자의 포스트 수정 시도
    $response = $this->putJson("/api/v1/posts/{$post->id}", $updateData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 권한 부족으로 실패
    $response->assertStatus(403);
});

test('포스트_발행_상태를_변경할_수_있다', function () {
    // Given: 초안 상태의 포스트
    $post = Post::factory()->create([
        'user_id' => $this->authorUser->id,
        'status' => 'draft',
        'published_at' => null,
    ]);
    
    $this->actingAs($this->authorUser);
    
    // When: 포스트 발행 API 호출
    $response = $this->postJson("/api/v1/posts/{$post->id}/publish", [], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 포스트가 발행됨
    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('published');
    expect($response->json('data.published_at'))->not->toBeNull();
    
    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'status' => 'published',
    ]);
});

test('포스트를_비공개로_전환할_수_있다', function () {
    // Given: 발행된 포스트
    $post = Post::factory()->create([
        'user_id' => $this->authorUser->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    
    $this->actingAs($this->authorUser);
    
    // When: 포스트 비공개 API 호출
    $response = $this->postJson("/api/v1/posts/{$post->id}/unpublish", [], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 포스트가 비공개됨
    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('draft');
});

test('포스트_슬러그를_생성할_수_있다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $titleData = [
        'title' => '한글 포스트 제목입니다'
    ];
    
    // When: 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/posts/generate-slug', $titleData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 한글 슬러그가 생성됨
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['slug']
        ]);
    
    expect($response->json('data.slug'))->toContain('한글-포스트-제목');
});

test('포스트_삭제가_가능하다', function () {
    // Given: 작성자가 작성한 포스트
    $post = Post::factory()->create([
        'user_id' => $this->authorUser->id,
        'category_id' => $this->category->id,
    ]);
    
    $this->actingAs($this->authorUser);
    
    // When: 포스트 삭제 API 호출
    $response = $this->deleteJson("/api/v1/posts/{$post->id}", [], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 포스트가 삭제됨
    $response->assertStatus(200);
    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});

test('포스트_유효성_검사가_작동한다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $invalidData = [
        'title' => '', // 제목 누락
        'content' => '', // 내용 누락
        'category_id' => 999, // 존재하지 않는 카테고리
    ];
    
    // When: 잘못된 데이터로 포스트 생성 시도
    $response = $this->postJson('/api/v1/posts', $invalidData, [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 유효성 검사 오류 반환
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'content', 'category_id']);
});

test('관련_포스트_조회가_가능하다', function () {
    // Given: 같은 카테고리의 포스트들 생성
    $mainPost = Post::factory()->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'category_id' => $this->category->id,
    ]);
    
    $relatedPosts = Post::factory()->count(3)->create([
        'status' => 'published',
        'published_at' => now()->subDays(2),
        'category_id' => $this->category->id,
    ]);
    
    // When: 관련 포스트 조회
    $response = $this->getJson("/api/v1/posts/{$mainPost->id}/related");
    
    // Then: 관련 포스트 목록 반환
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'slug',
                    'excerpt',
                    'category',
                    'published_at'
                ]
            ]
        ]);
    
    // 메인 포스트는 제외됨
    $returnedIds = collect($response->json('data'))->pluck('id');
    expect($returnedIds)->not->toContain($mainPost->id);
});