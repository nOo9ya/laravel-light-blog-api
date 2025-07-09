<?php

/**
 * 테스트 목적: 첨부파일 관리 시스템 테스트
 * 테스트 시나리오: 파일 업로드, 다운로드, 관리, 타입별 분류
 * 기대 결과: 첨부파일 시스템이 정상 작동
 * 관련 비즈니스 규칙: 관리자/작성자만 업로드 가능, 파일 타입 제한, 크기 제한
 */

use App\Models\User;
use App\Models\Post;
use App\Models\PostAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Given: 테스트용 사용자 및 포스트 생성
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->authorUser = User::factory()->create(['role' => 'author']);
    $this->normalUser = User::factory()->create(['role' => 'user']);
    
    $this->post = Post::factory()->create([
        'user_id' => $this->authorUser->id,
        'status' => 'published',
    ]);
    
    // JWT 토큰 생성
    $this->adminToken = auth()->login($this->adminUser);
    $this->authorToken = auth()->login($this->authorUser);
    $this->userToken = auth()->login($this->normalUser);
    
    // 테스트용 스토리지 설정
    Storage::fake('public');
});

test('작성자가_포스트에_첨부파일을_업로드할_수_있다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    // 테스트용 PDF 파일 생성
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
    
    // When: 첨부파일 업로드 API 호출
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/attachments", [
        'file' => $file,
        'title' => '첨부 문서',
        'description' => '중요한 문서입니다.',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 첨부파일이 성공적으로 업로드됨
    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'title',
                'description',
                'original_name',
                'file_name',
                'file_path',
                'file_size',
                'mime_type',
                'file_type',
                'download_count',
                'uploaded_at'
            ]
        ]);
    
    expect($response->json('data.original_name'))->toBe('document.pdf');
    expect($response->json('data.title'))->toBe('첨부 문서');
    expect($response->json('data.file_type'))->toBe('document');
    expect($response->json('data.mime_type'))->toBe('application/pdf');
    
    // 데이터베이스 확인
    $this->assertDatabaseHas('post_attachments', [
        'post_id' => $this->post->id,
        'original_name' => 'document.pdf',
        'title' => '첨부 문서',
    ]);
});

test('관리자가_첨부파일을_업로드할_수_있다', function () {
    // Given: 관리자로 인증
    $this->actingAs($this->adminUser);
    
    $file = UploadedFile::fake()->create('admin-file.zip', 2048, 'application/zip');
    
    // When: 첨부파일 업로드 API 호출
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/attachments", [
        'file' => $file,
        'title' => '관리자 파일',
    ], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 첨부파일이 성공적으로 업로드됨
    $response->assertStatus(201);
    expect($response->json('data.file_type'))->toBe('archive');
});

test('일반_사용자는_첨부파일을_업로드할_수_없다', function () {
    // Given: 일반 사용자로 인증
    $this->actingAs($this->normalUser);
    
    $file = UploadedFile::fake()->create('user-file.pdf', 1024);
    
    // When: 첨부파일 업로드 시도
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/attachments", [
        'file' => $file,
        'title' => '사용자 파일',
    ], [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 권한 부족으로 실패
    $response->assertStatus(403);
});

test('다양한_파일_타입을_업로드할_수_있다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $testFiles = [
        ['file' => UploadedFile::fake()->create('document.docx', 1024, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'), 'type' => 'document'],
        ['file' => UploadedFile::fake()->create('spreadsheet.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), 'type' => 'document'],
        ['file' => UploadedFile::fake()->create('presentation.pptx', 1024, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'), 'type' => 'document'],
        ['file' => UploadedFile::fake()->create('archive.zip', 1024, 'application/zip'), 'type' => 'archive'],
        ['file' => UploadedFile::fake()->create('text.txt', 512, 'text/plain'), 'type' => 'text'],
    ];
    
    foreach ($testFiles as $index => $testFile) {
        // When: 각 파일 타입 업로드
        $response = $this->postJson("/api/v1/posts/{$this->post->id}/attachments", [
            'file' => $testFile['file'],
            'title' => "테스트 파일 {$index}",
        ], [
            'Authorization' => 'Bearer ' . $this->authorToken
        ]);
        
        // Then: 올바른 파일 타입으로 분류됨
        $response->assertStatus(201);
        expect($response->json('data.file_type'))->toBe($testFile['type']);
    }
});

test('포스트의_첨부파일_목록을_조회할_수_있다', function () {
    // Given: 포스트에 첨부파일들 생성
    $attachments = PostAttachment::factory()->count(3)->create([
        'post_id' => $this->post->id,
    ]);
    
    // When: 첨부파일 목록 조회
    $response = $this->getJson("/api/v1/posts/{$this->post->id}/attachments");
    
    // Then: 첨부파일 목록이 반환됨
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'original_name',
                    'file_size',
                    'file_type',
                    'download_count',
                    'uploaded_at'
                ]
            ]
        ]);
    
    expect($response->json('data'))->toHaveCount(3);
});

test('첨부파일을_다운로드할_수_있다', function () {
    // Given: 첨부파일 생성
    $this->actingAs($this->authorUser);
    
    $file = UploadedFile::fake()->create('download-test.pdf', 1024);
    $uploadResponse = $this->postJson("/api/v1/posts/{$this->post->id}/attachments", [
        'file' => $file,
        'title' => '다운로드 테스트',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    $attachmentId = $uploadResponse->json('data.id');
    
    // When: 첨부파일 다운로드 API 호출
    $response = $this->getJson("/api/v1/attachments/{$attachmentId}/download");
    
    // Then: 파일 다운로드 응답
    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    
    // 다운로드 횟수 증가 확인
    $this->assertDatabaseHas('post_attachments', [
        'id' => $attachmentId,
        'download_count' => 1,
    ]);
});

test('첨부파일_정보를_수정할_수_있다', function () {
    // Given: 작성자가 업로드한 첨부파일
    $attachment = PostAttachment::factory()->create([
        'post_id' => $this->post->id,
        'title' => '원본 제목',
        'description' => '원본 설명',
    ]);
    
    $this->actingAs($this->authorUser);
    
    // When: 첨부파일 정보 수정
    $response = $this->putJson("/api/v1/attachments/{$attachment->id}", [
        'title' => '수정된 제목',
        'description' => '수정된 설명',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 첨부파일 정보가 수정됨
    $response->assertStatus(200);
    expect($response->json('data.title'))->toBe('수정된 제목');
    expect($response->json('data.description'))->toBe('수정된 설명');
    
    $this->assertDatabaseHas('post_attachments', [
        'id' => $attachment->id,
        'title' => '수정된 제목',
        'description' => '수정된 설명',
    ]);
});

test('첨부파일을_삭제할_수_있다', function () {
    // Given: 작성자가 업로드한 첨부파일
    $this->actingAs($this->authorUser);
    
    $file = UploadedFile::fake()->create('delete-test.pdf', 1024);
    $uploadResponse = $this->postJson("/api/v1/posts/{$this->post->id}/attachments", [
        'file' => $file,
        'title' => '삭제 테스트',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    $attachmentId = $uploadResponse->json('data.id');
    $filePath = $uploadResponse->json('data.file_path');
    
    // When: 첨부파일 삭제 API 호출
    $response = $this->deleteJson("/api/v1/attachments/{$attachmentId}", [], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 첨부파일이 삭제됨
    $response->assertStatus(200);
    
    // 데이터베이스에서 삭제됨
    $this->assertDatabaseMissing('post_attachments', ['id' => $attachmentId]);
    
    // 파일이 실제로 삭제됨
    Storage::disk('public')->assertMissing($filePath);
});

test('파일_크기_제한이_적용된다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    // 큰 파일 생성 (50MB)
    $largeFile = UploadedFile::fake()->create('large-file.pdf', 51200); // 50MB
    
    // When: 큰 파일 업로드 시도
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/attachments", [
        'file' => $largeFile,
        'title' => '큰 파일',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 파일 크기 제한으로 실패
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('허용되지_않는_파일_형식은_거부된다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    // 허용되지 않는 파일 형식 (.exe)
    $invalidFile = UploadedFile::fake()->create('virus.exe', 1024, 'application/x-msdownload');
    
    // When: 허용되지 않는 파일 업로드 시도
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/attachments", [
        'file' => $invalidFile,
        'title' => '위험한 파일',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 파일 형식 검증으로 실패
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('관리자가_모든_첨부파일_목록을_조회할_수_있다', function () {
    // Given: 다양한 포스트의 첨부파일들 생성
    $otherPost = Post::factory()->create();
    
    PostAttachment::factory()->count(2)->create(['post_id' => $this->post->id]);
    PostAttachment::factory()->count(3)->create(['post_id' => $otherPost->id]);
    
    $this->actingAs($this->adminUser);
    
    // When: 전체 첨부파일 목록 조회
    $response = $this->getJson('/api/v1/admin/attachments?per_page=10', [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 모든 첨부파일이 반환됨
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'original_name',
                        'file_size',
                        'file_type',
                        'post',
                        'uploaded_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page'
                ]
            ]
        ]);
    
    expect($response->json('data.data'))->toHaveCount(5);
});

test('첨부파일_통계를_조회할_수_있다', function () {
    // Given: 다양한 타입의 첨부파일들 생성
    $this->actingAs($this->adminUser);
    
    PostAttachment::factory()->count(3)->create(['file_type' => 'document']);
    PostAttachment::factory()->count(2)->create(['file_type' => 'archive']);
    PostAttachment::factory()->create(['file_type' => 'image']);
    
    // When: 첨부파일 통계 조회
    $response = $this->getJson('/api/v1/admin/attachments/stats', [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 통계 정보가 반환됨
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'total_files',
                'total_size',
                'by_type',
                'recent_uploads'
            ]
        ]);
    
    $stats = $response->json('data');
    expect($stats['total_files'])->toBe(6);
    expect($stats['by_type']['document'])->toBe(3);
    expect($stats['by_type']['archive'])->toBe(2);
    expect($stats['by_type']['image'])->toBe(1);
});

test('첨부파일_유효성_검사가_작동한다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $file = UploadedFile::fake()->create('test.pdf', 1024);
    
    // When: 제목 없이 첨부파일 업로드 시도
    $response = $this->postJson("/api/v1/posts/{$this->post->id}/attachments", [
        'file' => $file,
        'title' => '', // 제목 누락
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 유효성 검사 오류 반환
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

test('존재하지_않는_첨부파일_다운로드시_404_반환', function () {
    // Given: 존재하지 않는 첨부파일 ID
    $fakeId = 999;
    
    // When: 존재하지 않는 첨부파일 다운로드 시도
    $response = $this->getJson("/api/v1/attachments/{$fakeId}/download");
    
    // Then: 404 오류 반환
    $response->assertStatus(404);
});