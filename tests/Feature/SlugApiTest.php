<?php

use App\Models\User;
use App\Models\Post;
use App\Models\Page;
use App\Models\Category;

/**
 * Slug API 테스트
 * 
 * 한글 슬러그 자동 생성 API의 기능을 검증합니다.
 * - 한글 제목에서 슬러그 생성
 * - 영문 제목에서 슬러그 생성
 * - 슬러그 유효성 검사
 * - 슬러그 중복 확인
 * - 슬러그 일괄 생성 (관리자 전용)
 */

test('한글_제목에서_슬러그_생성_성공', function () {
    // Given: 한글 제목 데이터 준비
    $requestData = [
        'title' => '안녕하세요 첫 번째 포스트입니다',
        'method' => 'auto',
        'type' => 'post'
    ];

    // When: 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/generate', $requestData);

    // Then: 성공 응답 및 한글 슬러그 생성 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '슬러그가 생성되었습니다'
        ])
        ->assertJsonStructure([
            'data' => [
                'original_title',
                'generated_slug',
                'unique_slug',
                'method_used',
                'separator',
                'is_unique',
                'validation' => [
                    'is_valid',
                    'errors'
                ],
                'url_preview',
                'character_count',
                'contains_korean',
                'content_type',
                'suggestions'
            ]
        ]);

    expect($response['data']['original_title'])->toBe('안녕하세요 첫 번째 포스트입니다');
    expect($response['data']['generated_slug'])->toBe('안녕하세요-첫-번째-포스트입니다');
    expect($response['data']['contains_korean'])->toBe(true);
    expect($response['data']['validation']['is_valid'])->toBe(true);
    expect($response['data']['is_unique'])->toBe(true);
});

test('영문_제목에서_슬러그_생성_성공', function () {
    // Given: 영문 제목 데이터 준비
    $requestData = [
        'title' => 'Hello World My First Blog Post',
        'method' => 'auto',
        'type' => 'post'
    ];

    // When: 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/generate', $requestData);

    // Then: 성공 응답 및 영문 슬러그 생성 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'original_title' => 'Hello World My First Blog Post',
                'generated_slug' => 'hello-world-my-first-blog-post',
                'contains_korean' => false,
                'method_used' => 'auto'
            ]
        ]);

    expect($response['data']['validation']['is_valid'])->toBe(true);
    expect($response['data']['character_count'])->toBe(30);
});

test('한글_방식_강제_적용_성공', function () {
    // Given: 영문이 포함된 제목에 한글 방식 강제 적용
    $requestData = [
        'title' => 'Laravel 개발 가이드',
        'method' => 'korean',
        'type' => 'post'
    ];

    // When: 한글 방식으로 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/generate', $requestData);

    // Then: 한글 방식으로 생성된 슬러그 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'original_title' => 'Laravel 개발 가이드',
                'generated_slug' => 'laravel-개발-가이드',
                'method_used' => 'korean',
                'contains_korean' => true
            ]
        ]);
});

test('영문_방식_강제_적용_성공', function () {
    // Given: 한글이 포함된 제목에 영문 방식 강제 적용
    $requestData = [
        'title' => '라라벨 Laravel 가이드',
        'method' => 'english',
        'type' => 'post'
    ];

    // When: 영문 방식으로 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/generate', $requestData);

    // Then: 영문 방식으로 생성된 슬러그 확인 (한글 제거됨)
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'original_title' => '라라벨 Laravel 가이드',
                'generated_slug' => 'laravel',
                'method_used' => 'english',
                'contains_korean' => false
            ]
        ]);
});

test('언더스코어_구분자_사용_성공', function () {
    // Given: 언더스코어 구분자 사용 요청
    $requestData = [
        'title' => '테스트 포스트 제목',
        'method' => 'auto',
        'separator' => '_',
        'type' => 'post'
    ];

    // When: 언더스코어 구분자로 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/generate', $requestData);

    // Then: 언더스코어로 구분된 슬러그 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'original_title' => '테스트 포스트 제목',
                'generated_slug' => '테스트_포스트_제목',
                'separator' => '_'
            ]
        ]);
});

test('슬러그_중복_시_고유번호_추가', function () {
    // Given: 기존 포스트와 동일한 제목의 슬러그 생성 요청
    Post::factory()->create([
        'title' => '중복 테스트',
        'slug' => '중복-테스트'
    ]);

    $requestData = [
        'title' => '중복 테스트',
        'method' => 'auto',
        'type' => 'post'
    ];

    // When: 중복되는 제목으로 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/generate', $requestData);

    // Then: 중복 방지를 위한 고유 슬러그 생성 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'original_title' => '중복 테스트',
                'generated_slug' => '중복-테스트',
                'unique_slug' => '중복-테스트-1',
                'is_unique' => false
            ]
        ]);
});

test('다양한_콘텐츠_타입별_슬러그_생성_성공', function () {
    // Given: 페이지와 카테고리 타입 테스트
    $pageData = [
        'title' => '회사 소개 페이지',
        'type' => 'page'
    ];
    
    $categoryData = [
        'title' => '기술 카테고리',
        'type' => 'category'
    ];

    // When: 각각 다른 타입으로 슬러그 생성
    $pageResponse = $this->postJson('/api/v1/slugs/generate', $pageData);
    $categoryResponse = $this->postJson('/api/v1/slugs/generate', $categoryData);

    // Then: 각 타입별로 적절한 슬러그 생성 확인
    $pageResponse->assertStatus(200)
        ->assertJson([
            'data' => [
                'content_type' => 'page',
                'generated_slug' => '회사-소개-페이지'
            ]
        ]);

    $categoryResponse->assertStatus(200)
        ->assertJson([
            'data' => [
                'content_type' => 'category',
                'generated_slug' => '기술-카테고리'
            ]
        ]);
});

test('슬러그_유효성_검사_성공', function () {
    // Given: 유효한 슬러그 데이터
    $validSlugData = [
        'slug' => '유효한-슬러그-테스트',
        'type' => 'post'
    ];

    // When: 슬러그 유효성 검사 API 호출
    $response = $this->postJson('/api/v1/slugs/validate', $validSlugData);

    // Then: 유효성 검사 통과 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '슬러그 유효성 검사가 완료되었습니다',
            'data' => [
                'slug' => '유효한-슬러그-테스트',
                'is_valid' => true,
                'is_unique' => true,
                'validation_errors' => []
            ]
        ]);
});

test('슬러그_유효성_검사_실패', function () {
    // Given: 유효하지 않은 슬러그 데이터 (너무 짧음, 특수문자 포함)
    $invalidSlugData = [
        'slug' => 'a!',
        'type' => 'post'
    ];

    // When: 잘못된 슬러그로 유효성 검사 API 호출
    $response = $this->postJson('/api/v1/slugs/validate', $invalidSlugData);

    // Then: 유효성 검사 실패 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'slug' => 'a!',
                'is_valid' => false
            ]
        ]);

    expect($response['data']['validation_errors'])->not->toBeEmpty();
});

test('슬러그_중복_확인_및_대안_제안', function () {
    // Given: 기존 포스트와 동일한 슬러그로 유효성 검사
    Post::factory()->create(['slug' => '기존-포스트']);

    $duplicateSlugData = [
        'slug' => '기존-포스트',
        'type' => 'post'
    ];

    // When: 중복된 슬러그로 유효성 검사 API 호출
    $response = $this->postJson('/api/v1/slugs/validate', $duplicateSlugData);

    // Then: 중복 확인 및 대안 슬러그 제안 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'slug' => '기존-포스트',
                'is_valid' => true,
                'is_unique' => false,
                'suggested_slug' => '기존-포스트-1'
            ]
        ]);
});

test('관리자_일괄_슬러그_생성_성공', function () {
    // Given: 관리자 사용자 로그인 및 슬러그가 없는 포스트들 생성
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');

    Post::factory()->count(5)->create([
        'slug' => null,
        'title' => '슬러그 없는 포스트'
    ]);

    $batchData = [
        'type' => 'post',
        'method' => 'auto',
        'force_update' => false
    ];

    // When: 일괄 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/batch-generate', $batchData);

    // Then: 일괄 생성 성공 및 결과 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '슬러그 일괄 생성이 완료되었습니다'
        ])
        ->assertJsonStructure([
            'data' => [
                'total_processed',
                'updated_count',
                'skipped_count',
                'failed_count',
                'processing_time',
                'content_type',
                'method_used',
                'force_update'
            ]
        ]);

    expect($response['data']['total_processed'])->toBe(5);
    expect($response['data']['updated_count'])->toBe(5);
    expect($response['data']['failed_count'])->toBe(0);
});

test('일반_사용자_일괄_슬러그_생성시_권한_없음_에러', function () {
    // Given: 일반 사용자 로그인
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user, 'api');

    $batchData = [
        'type' => 'post',
        'method' => 'auto'
    ];

    // When: 일괄 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/batch-generate', $batchData);

    // Then: 권한 없음 에러 응답 확인
    $response->assertStatus(403);
});

test('슬러그_제안_기능_확인', function () {
    // Given: 슬러그 생성 요청
    $requestData = [
        'title' => '제안 테스트 포스트',
        'method' => 'auto',
        'type' => 'post'
    ];

    // When: 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/generate', $requestData);

    // Then: 다양한 방식의 제안이 포함되어 있는지 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'suggestions' => [
                    'korean',
                    'underscore'
                ]
            ]
        ]);

    expect($response['data']['suggestions'])->toHaveKey('korean');
    expect($response['data']['suggestions'])->toHaveKey('underscore');
});

test('빈_제목_처리_및_랜덤_슬러그_생성', function () {
    // Given: 빈 제목이나 특수문자만 있는 제목
    $requestData = [
        'title' => '!@#$%^&*()',
        'method' => 'auto',
        'type' => 'post'
    ];

    // When: 특수문자만 있는 제목으로 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/generate', $requestData);

    // Then: 랜덤 슬러그가 생성되는지 확인
    $response->assertStatus(200);
    
    $generatedSlug = $response['data']['generated_slug'];
    expect($generatedSlug)->toStartWith('page-');
    expect(strlen($generatedSlug))->toBe(13); // 'page-' + 8자 랜덤 문자열
});

test('슬러그_길이_제한_확인', function () {
    // Given: 매우 긴 제목
    $longTitle = str_repeat('매우긴제목테스트', 20);
    
    $requestData = [
        'title' => $longTitle,
        'method' => 'auto',
        'type' => 'post'
    ];

    // When: 긴 제목으로 슬러그 생성 API 호출
    $response = $this->postJson('/api/v1/slugs/generate', $requestData);

    // Then: 생성된 슬러그가 적절한 길이인지 확인
    $response->assertStatus(200);
    
    $generatedSlug = $response['data']['generated_slug'];
    expect(strlen($generatedSlug))->toBeLessThanOrEqual(100);
});