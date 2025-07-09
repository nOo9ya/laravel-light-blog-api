<?php

/**
 * 테스트 목적: 댓글 시스템 및 스팸 필터링 테스트
 * 테스트 시나리오: 댓글 작성, 대댓글, 승인/스팸 처리, 스팸 필터링
 * 기대 결과: 계층형 댓글 구조와 스팸 필터링이 정상 작동
 * 관련 비즈니스 규칙: 3단계 댓글, 비회원 댓글 지원, 자동 스팸 차단
 */

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Given: 테스트용 사용자 및 포스트 생성
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->normalUser = User::factory()->create(['role' => 'user']);
    
    $this->post = Post::factory()->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    
    // JWT 토큰 생성
    $this->adminToken = auth()->login($this->adminUser);
    $this->userToken = auth()->login($this->normalUser);
});

test('회원이_댓글을_작성할_수_있다', function () {
    // Given: 인증된 사용자
    $this->actingAs($this->normalUser);
    
    $commentData = [
        'content' => '좋은 글 감사합니다!',
    ];
    
    // When: 댓글 작성 API 호출
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/comments", $commentData, [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 댓글이 성공적으로 작성됨
    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'content',
                'user',
                'created_at',
                'is_approved',
                'replies_count'
            ]
        ]);
    
    expect($response->json('data.content'))->toBe('좋은 글 감사합니다!');
    expect($response->json('data.user.id'))->toBe($this->normalUser->id);
    
    $this->assertDatabaseHas('comments', [
        'post_id' => $this->post->id,
        'user_id' => $this->normalUser->id,
        'content' => '좋은 글 감사합니다!',
        'status' => 'approved', // 회원 댓글은 자동 승인
    ]);
});

test('비회원이_댓글을_작성할_수_있다', function () {
    // Given: 비인증 상태
    $commentData = [
        'content' => '비회원 댓글입니다.',
        'guest_name' => '홍길동',
        'guest_email' => 'guest@example.com',
        'guest_password' => 'password123',
    ];
    
    // When: 비회원 댓글 작성 API 호출
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/comments", $commentData);
    
    // Then: 댓글이 성공적으로 작성됨
    $response->assertStatus(201);
    expect($response->json('data.author_name'))->toBe('홍길동');
    expect($response->json('data.author_email'))->toBe('guest@example.com');
    
    $this->assertDatabaseHas('comments', [
        'post_id' => $this->post->id,
        'user_id' => null,
        'guest_name' => '홍길동',
        'guest_email' => 'guest@example.com',
        'content' => '비회원 댓글입니다.',
        'status' => 'pending', // 비회원 댓글은 승인 대기
    ]);
});

test('대댓글을_작성할_수_있다', function () {
    // Given: 부모 댓글 생성
    $parentComment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->normalUser->id,
        'status' => 'approved',
    ]);
    
    $this->actingAs($this->normalUser);
    
    $replyData = [
        'content' => '답글입니다.',
        'parent_id' => $parentComment->id,
    ];
    
    // When: 대댓글 작성 API 호출
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/comments", $replyData, [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 대댓글이 성공적으로 작성됨
    $response->assertStatus(201);
    expect($response->json('data.parent_id'))->toBe($parentComment->id);
    
    $this->assertDatabaseHas('comments', [
        'post_id' => $this->post->id,
        'parent_id' => $parentComment->id,
        'content' => '답글입니다.',
        'depth' => 1,
    ]);
});

test('3단계_이상_댓글은_작성할_수_없다', function () {
    // Given: 2단계 댓글 구조 생성
    $level1 = Comment::factory()->create([
        'post_id' => $this->post->id,
        'depth' => 0,
        'status' => 'approved',
    ]);
    
    $level2 = Comment::factory()->create([
        'post_id' => $this->post->id,
        'parent_id' => $level1->id,
        'depth' => 1,
        'status' => 'approved',
    ]);
    
    $level3 = Comment::factory()->create([
        'post_id' => $this->post->id,
        'parent_id' => $level2->id,
        'depth' => 2,
        'status' => 'approved',
    ]);
    
    $this->actingAs($this->normalUser);
    
    $replyData = [
        'content' => '4단계 댓글 시도',
        'parent_id' => $level3->id,
    ];
    
    // When: 4단계 댓글 작성 시도
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/comments", $replyData, [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 최대 깊이 초과로 실패
    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('MAX_DEPTH_EXCEEDED');
});

test('스팸_키워드가_포함된_댓글은_자동_차단된다', function () {
    // Given: 인증된 사용자
    $this->actingAs($this->normalUser);
    
    $spamCommentData = [
        'content' => '무료 카지노 바카라 게임! 100% 보장! 지금 가입하세요! 무료무료무료',
    ];
    
    // When: 스팸 키워드가 포함된 댓글 작성
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/comments", $spamCommentData, [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 댓글이 스팸으로 분류됨
    $response->assertStatus(201);
    expect($response->json('message'))->toContain('스팸으로 분류');
    
    $this->assertDatabaseHas('comments', [
        'post_id' => $this->post->id,
        'user_id' => $this->normalUser->id,
        'status' => 'spam',
    ]);
});

test('승인된_댓글_목록을_조회할_수_있다', function () {
    // Given: 승인된 댓글과 대기중인 댓글 생성
    $approvedComments = Comment::factory()->count(3)->create([
        'post_id' => $this->post->id,
        'status' => 'approved',
    ]);
    
    $pendingComment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'status' => 'pending',
    ]);
    
    // When: 댓글 목록 조회
    $response = $this->getJson("/api/v1/posts/{$this->post->id}/comments");
    
    // Then: 승인된 댓글만 반환됨
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'content',
                        'author_name',
                        'created_at',
                        'replies'
                    ]
                ]
            ]
        ]);
    
    expect($response->json('data.data'))->toHaveCount(3);
    
    // 대기중인 댓글은 포함되지 않음
    $returnedIds = collect($response->json('data.data'))->pluck('id');
    expect($returnedIds)->not->toContain($pendingComment->id);
});

test('댓글을_수정할_수_있다', function () {
    // Given: 사용자가 작성한 댓글
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->normalUser->id,
        'content' => '원본 댓글',
        'status' => 'approved',
    ]);
    
    $this->actingAs($this->normalUser);
    
    $updateData = [
        'content' => '수정된 댓글 내용',
    ];
    
    // When: 댓글 수정 API 호출
    $response = $this->putJson("/api/v1/comments/{$comment->id}", $updateData, [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 댓글이 성공적으로 수정됨
    $response->assertStatus(200);
    expect($response->json('data.content'))->toBe('수정된 댓글 내용');
    
    $this->assertDatabaseHas('comments', [
        'id' => $comment->id,
        'content' => '수정된 댓글 내용',
    ]);
});

test('비회원_댓글을_비밀번호로_수정할_수_있다', function () {
    // Given: 비회원이 작성한 댓글
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'guest_name' => '홍길동',
        'guest_email' => 'guest@example.com',
        'guest_password' => bcrypt('password123'),
        'content' => '원본 비회원 댓글',
        'status' => 'approved',
    ]);
    
    $updateData = [
        'content' => '수정된 비회원 댓글',
        'guest_password' => 'password123',
    ];
    
    // When: 비밀번호와 함께 댓글 수정
    $response = $this->putJson("/api/v1/comments/{$comment->id}", $updateData);
    
    // Then: 댓글이 성공적으로 수정됨
    $response->assertStatus(200);
    expect($response->json('data.content'))->toBe('수정된 비회원 댓글');
});

test('잘못된_비밀번호로는_비회원_댓글을_수정할_수_없다', function () {
    // Given: 비회원이 작성한 댓글
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'guest_name' => '홍길동',
        'guest_password' => bcrypt('password123'),
        'content' => '원본 비회원 댓글',
        'status' => 'approved',
    ]);
    
    $updateData = [
        'content' => '수정된 비회원 댓글',
        'guest_password' => 'wrongpassword',
    ];
    
    // When: 잘못된 비밀번호로 댓글 수정 시도
    $response = $this->putJson("/api/v1/comments/{$comment->id}", $updateData);
    
    // Then: 비밀번호 불일치로 실패
    $response->assertStatus(403);
});

test('관리자가_댓글을_승인할_수_있다', function () {
    // Given: 승인 대기중인 댓글
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'status' => 'pending',
    ]);
    
    $this->actingAs($this->adminUser);
    
    // When: 댓글 승인 API 호출
    $response = $this->postJson("/api/v1/comments/{$comment->id}/approve", [], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 댓글이 승인됨
    $response->assertStatus(200);
    expect($response->json('data.is_approved'))->toBeTrue();
    
    $this->assertDatabaseHas('comments', [
        'id' => $comment->id,
        'status' => 'approved',
        'approved_by' => $this->adminUser->id,
    ]);
});

test('관리자가_댓글을_스팸으로_처리할_수_있다', function () {
    // Given: 승인된 댓글
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'status' => 'approved',
    ]);
    
    $this->actingAs($this->adminUser);
    
    // When: 댓글 스팸 처리 API 호출
    $response = $this->postJson("/api/v1/comments/{$comment->id}/spam", [], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 댓글이 스팸으로 처리됨
    $response->assertStatus(200);
    expect($response->json('data.is_spam'))->toBeTrue();
    
    $this->assertDatabaseHas('comments', [
        'id' => $comment->id,
        'status' => 'spam',
    ]);
});

test('댓글을_삭제할_수_있다', function () {
    // Given: 사용자가 작성한 댓글 (하위 댓글 없음)
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->normalUser->id,
        'status' => 'approved',
    ]);
    
    $this->actingAs($this->normalUser);
    
    // When: 댓글 삭제 API 호출
    $response = $this->deleteJson("/api/v1/comments/{$comment->id}", [], [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 댓글이 완전히 삭제됨
    $response->assertStatus(200);
    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

test('하위_댓글이_있는_경우_소프트_삭제된다', function () {
    // Given: 하위 댓글이 있는 부모 댓글
    $parentComment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->normalUser->id,
        'status' => 'approved',
    ]);
    
    $childComment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'parent_id' => $parentComment->id,
        'status' => 'approved',
    ]);
    
    $this->actingAs($this->normalUser);
    
    // When: 부모 댓글 삭제 시도
    $response = $this->deleteJson("/api/v1/comments/{$parentComment->id}", [], [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 소프트 삭제됨 (답글 보호)
    $response->assertStatus(200);
    expect($response->json('message'))->toContain('답글 보호');
    
    $this->assertDatabaseHas('comments', [
        'id' => $parentComment->id,
        'content' => '[deleted]',
        'is_deleted' => true,
    ]);
});

test('관리자_댓글_목록을_조회할_수_있다', function () {
    // Given: 다양한 상태의 댓글들
    Comment::factory()->count(2)->create(['status' => 'approved']);
    Comment::factory()->count(3)->create(['status' => 'pending']);
    Comment::factory()->create(['status' => 'spam']);
    
    $this->actingAs($this->adminUser);
    
    // When: 관리자 댓글 목록 조회
    $response = $this->getJson('/api/v1/admin/comments', [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 모든 댓글이 반환됨
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'content',
                        'status',
                        'author_name',
                        'post',
                        'created_at'
                    ]
                ]
            ]
        ]);
    
    expect($response->json('data.data'))->toHaveCount(6);
});

test('댓글_유효성_검사가_작동한다', function () {
    // Given: 인증된 사용자
    $this->actingAs($this->normalUser);
    
    $invalidData = [
        'content' => '', // 내용 누락
    ];
    
    // When: 잘못된 데이터로 댓글 작성 시도
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/comments", $invalidData, [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 유효성 검사 오류 반환
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

test('존재하지_않는_포스트에는_댓글을_작성할_수_없다', function () {
    // Given: 인증된 사용자
    $this->actingAs($this->normalUser);
    
    $commentData = [
        'content' => '댓글 내용',
    ];
    
    // When: 존재하지 않는 포스트에 댓글 작성 시도
    $response = $this->postJson('/api/v1/posts/999/comments', $commentData, [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 404 오류 반환
    $response->assertStatus(404);
});