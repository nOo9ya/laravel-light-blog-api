<?php

/**
 * 테스트 목적: 미디어 파일 업로드 및 이미지 처리 테스트
 * 테스트 시나리오: 이미지 업로드, WebP 변환, 리사이징, OG 이미지 처리
 * 기대 결과: 이미지 업로드 및 변환 기능이 정상 작동
 * 관련 비즈니스 규칙: 관리자/작성자만 업로드 가능, WebP 자동 변환, 파일 크기 제한
 */

use App\Models\User;
use App\Models\Post;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Given: 테스트용 사용자 생성
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->authorUser = User::factory()->create(['role' => 'author']);
    $this->normalUser = User::factory()->create(['role' => 'user']);
    
    // 테스트용 포스트 생성
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

test('관리자가_이미지를_업로드할_수_있다', function () {
    // Given: 관리자로 인증
    $this->actingAs($this->adminUser);
    
    // 테스트용 이미지 파일 생성
    $image = UploadedFile::fake()->image('test-image.jpg', 1200, 800);
    
    // When: 이미지 업로드 API 호출
    $response = $this->postJson('/api/v1/media/upload', [
        'file' => $image,
        'type' => 'image',
        'post_id' => $this->post->id,
    ], [
        'Authorization' => 'Bearer ' . $this->adminToken
    ]);
    
    // Then: 이미지가 성공적으로 업로드됨
    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'original_name',
                'file_name',
                'file_path',
                'file_size',
                'mime_type',
                'dimensions',
                'webp_path',
                'uploaded_at'
            ]
        ]);
    
    expect($response->json('data.original_name'))->toBe('test-image.jpg');
    expect($response->json('data.mime_type'))->toBe('image/jpeg');
    expect($response->json('data.webp_path'))->not->toBeNull();
});

test('작성자가_이미지를_업로드할_수_있다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $image = UploadedFile::fake()->image('author-image.png', 800, 600);
    
    // When: 이미지 업로드 API 호출
    $response = $this->postJson('/api/v1/media/upload', [
        'file' => $image,
        'type' => 'image',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 이미지가 성공적으로 업로드됨
    $response->assertStatus(201);
    expect($response->json('data.original_name'))->toBe('author-image.png');
});

test('일반_사용자는_이미지를_업로드할_수_없다', function () {
    // Given: 일반 사용자로 인증
    $this->actingAs($this->normalUser);
    
    $image = UploadedFile::fake()->image('user-image.jpg', 800, 600);
    
    // When: 이미지 업로드 시도
    $response = $this->postJson('/api/v1/media/upload', [
        'file' => $image,
        'type' => 'image',
    ], [
        'Authorization' => 'Bearer ' . $this->userToken
    ]);
    
    // Then: 권한 부족으로 실패
    $response->assertStatus(403);
});

test('WebP_변환이_자동으로_수행된다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $image = UploadedFile::fake()->image('original.jpg', 1000, 800);
    
    // When: 이미지 업로드
    $response = $this->postJson('/api/v1/media/upload', [
        'file' => $image,
        'type' => 'image',
        'convert_webp' => true,
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: WebP 파일이 생성됨
    $response->assertStatus(201);
    
    $data = $response->json('data');
    expect($data['webp_path'])->not->toBeNull();
    expect($data['webp_path'])->toContain('.webp');
    
    // 파일이 실제로 저장되었는지 확인
    Storage::disk('public')->assertExists($data['webp_path']);
});

test('이미지_리사이징이_정상_작동한다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $image = UploadedFile::fake()->image('large-image.jpg', 2000, 1500);
    
    // When: 리사이징 옵션과 함께 업로드
    $response = $this->postJson('/api/v1/media/upload', [
        'file' => $image,
        'type' => 'image',
        'resize' => true,
        'width' => 800,
        'height' => 600,
        'maintain_aspect' => true,
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 리사이징된 이미지가 생성됨
    $response->assertStatus(201);
    
    $data = $response->json('data');
    expect($data['dimensions']['width'])->toBeLessThanOrEqual(800);
    expect($data['dimensions']['height'])->toBeLessThanOrEqual(600);
});

test('OG_이미지_업로드가_정상_작동한다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $ogImage = UploadedFile::fake()->image('og-image.jpg', 1200, 630);
    
    // When: OG 이미지 업로드
    $response = $this->postJson('/api/v1/media/upload-og', [
        'file' => $ogImage,
        'post_id' => $this->post->id,
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: OG 이미지가 성공적으로 업로드됨
    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'file_path',
                'dimensions',
                'file_size',
                'optimized'
            ]
        ]);
    
    $data = $response->json('data');
    expect($data['dimensions']['width'])->toBe(1200);
    expect($data['dimensions']['height'])->toBe(630);
    expect($data['optimized'])->toBeTrue();
});

test('잘못된_파일_형식은_거부된다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $invalidFile = UploadedFile::fake()->create('document.txt', 100);
    
    // When: 잘못된 파일 형식 업로드 시도
    $response = $this->postJson('/api/v1/media/upload', [
        'file' => $invalidFile,
        'type' => 'image',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 파일 형식 오류로 실패
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('파일_크기_제한이_적용된다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    // 큰 파일 생성 (10MB 이상)
    $largeFile = UploadedFile::fake()->create('large-file.jpg', 10240); // 10MB
    
    // When: 큰 파일 업로드 시도
    $response = $this->postJson('/api/v1/media/upload', [
        'file' => $largeFile,
        'type' => 'image',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 파일 크기 제한으로 실패
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('업로드된_이미지_목록을_조회할_수_있다', function () {
    // Given: 업로드된 이미지들 생성
    $this->actingAs($this->authorUser);
    
    // 여러 이미지 업로드
    $images = [];
    for ($i = 1; $i <= 3; $i++) {
        $image = UploadedFile::fake()->image("test-image-{$i}.jpg", 800, 600);
        $response = $this->postJson('/api/v1/media/upload', [
            'file' => $image,
            'type' => 'image',
        ], [
            'Authorization' => 'Bearer ' . $this->authorToken
        ]);
        $images[] = $response->json('data');
    }
    
    // When: 이미지 목록 조회
    $response = $this->getJson('/api/v1/media/images?per_page=10', [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 이미지 목록이 반환됨
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'original_name',
                        'file_path',
                        'file_size',
                        'dimensions',
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
    
    expect($response->json('data.data'))->toHaveCount(3);
});

test('이미지를_삭제할_수_있다', function () {
    // Given: 업로드된 이미지
    $this->actingAs($this->authorUser);
    
    $image = UploadedFile::fake()->image('delete-test.jpg', 800, 600);
    $uploadResponse = $this->postJson('/api/v1/media/upload', [
        'file' => $image,
        'type' => 'image',
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    $imageId = $uploadResponse->json('data.id');
    
    // When: 이미지 삭제 API 호출
    $response = $this->deleteJson("/api/v1/media/{$imageId}", [], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 이미지가 삭제됨
    $response->assertStatus(200);
    
    // 파일이 실제로 삭제되었는지 확인
    $filePath = $uploadResponse->json('data.file_path');
    Storage::disk('public')->assertMissing($filePath);
});

test('ImageService_직접_테스트', function () {
    // Given: ImageService 인스턴스
    $imageService = app(ImageService::class);
    
    // 테스트용 이미지 생성
    $image = UploadedFile::fake()->image('service-test.jpg', 1000, 800);
    
    // When: 이미지 처리 서비스 호출
    $result = $imageService->processImage($image, [
        'width' => 600,
        'height' => 400,
        'convert_webp' => true,
        'quality' => 85,
    ]);
    
    // Then: 처리 결과 확인
    expect($result)->toHaveKeys([
        'original_path',
        'webp_path',
        'dimensions',
        'file_size',
        'processed'
    ]);
    
    expect($result['processed'])->toBeTrue();
    expect($result['dimensions']['width'])->toBe(600);
    expect($result['dimensions']['height'])->toBe(400);
});

test('썸네일_생성이_정상_작동한다', function () {
    // Given: 작성자로 인증
    $this->actingAs($this->authorUser);
    
    $image = UploadedFile::fake()->image('thumbnail-test.jpg', 1200, 800);
    
    // When: 썸네일 생성 옵션과 함께 업로드
    $response = $this->postJson('/api/v1/media/upload', [
        'file' => $image,
        'type' => 'image',
        'create_thumbnails' => true,
        'thumbnail_sizes' => [
            ['width' => 150, 'height' => 150],
            ['width' => 300, 'height' => 200],
        ],
    ], [
        'Authorization' => 'Bearer ' . $this->authorToken
    ]);
    
    // Then: 썸네일이 생성됨
    $response->assertStatus(201);
    
    $data = $response->json('data');
    expect($data['thumbnails'])->toBeArray();
    expect($data['thumbnails'])->toHaveCount(2);
    
    // 각 썸네일 파일이 존재하는지 확인
    foreach ($data['thumbnails'] as $thumbnail) {
        Storage::disk('public')->assertExists($thumbnail['path']);
    }
});