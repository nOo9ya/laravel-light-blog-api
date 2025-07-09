<?php

use App\Models\User;
use App\Models\Page;
use App\Models\Category;

/**
 * Page API 테스트
 * 
 * 3단계 페이지 관련 API 엔드포인트들의 기능을 검증합니다.
 * - 페이지 목록 조회
 * - 페이지 상세 조회
 * - 메뉴용 페이지 목록 조회
 * - 페이지 생성/수정/삭제 (관리자 권한)
 * - 페이지 계층구조 관리
 */

test('페이지_목록_조회_성공', function () {
    // Given: 테스트용 페이지 데이터 준비
    Page::factory()->count(6)->create([
        'is_published' => true
    ]);

    // When: 페이지 목록 API 호출
    $response = $this->getJson('/api/v1/pages');

    // Then: 성공 응답 및 데이터 구조 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'slug',
                    'excerpt',
                    'template',
                    'is_published',
                    'show_in_menu',
                    'menu_title',
                    'featured_image',
                    'parent_id',
                    'parent',
                    'children',
                    'category',
                    'author' => [
                        'id',
                        'name',
                        'email'
                    ],
                    'meta_title',
                    'meta_description',
                    'views_count',
                    'reading_time',
                    'urls' => [
                        'self'
                    ],
                    'meta' => [
                        'word_count',
                        'character_count',
                        'template_info',
                        'last_modified'
                    ],
                    'created_at',
                    'updated_at'
                ]
            ],
            'meta',
            'message'
        ]);
    
    expect($response['data'])->toHaveCount(6);
});

test('페이지_상세_조회_성공', function () {
    // Given: 테스트용 페이지 생성
    $user = User::factory()->create();
    $page = Page::factory()->create([
        'title' => '테스트 페이지',
        'slug' => 'test-page',
        'content' => '이것은 테스트 페이지의 내용입니다.',
        'excerpt' => '테스트 페이지 요약',
        'template' => 'default',
        'is_published' => true,
        'user_id' => $user->id
    ]);

    // When: 페이지 상세 API 호출
    $response = $this->getJson("/api/v1/pages/{$page->slug}");

    // Then: 성공 응답 및 상세 데이터 확인 (content 포함)
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $page->id,
                'title' => '테스트 페이지',
                'slug' => 'test-page',
                'content' => '이것은 테스트 페이지의 내용입니다.',
                'excerpt' => '테스트 페이지 요약',
                'template' => 'default',
                'is_published' => true
            ]
        ]);
    
    // content가 상세 조회시에만 포함되는지 확인
    expect($response['data'])->toHaveKey('content');
});

test('존재하지_않는_페이지_조회시_404_반환', function () {
    // When: 존재하지 않는 페이지 조회
    $response = $this->getJson('/api/v1/pages/non-existent-page');

    // Then: 404 에러 응답 확인
    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND'
            ]
        ]);
});

test('메뉴용_페이지_목록_조회_성공', function () {
    // Given: 메뉴에 표시될 페이지와 표시되지 않을 페이지 생성
    Page::factory()->count(3)->create([
        'show_in_menu' => true,
        'is_published' => true,
        'sort_order' => 1
    ]);
    Page::factory()->count(2)->create([
        'show_in_menu' => false,
        'is_published' => true
    ]);

    // When: 메뉴용 페이지 목록 API 호출
    $response = $this->getJson('/api/v1/pages/menu');

    // Then: 메뉴에 표시될 페이지들만 반환 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'slug',
                    'menu_title',
                    'sort_order',
                    'navigation' => [
                        'menu_title',
                        'menu_order',
                        'has_children',
                        'breadcrumb_trail'
                    ]
                ]
            ],
            'message'
        ]);
    
    expect($response['data'])->toHaveCount(3);
    
    // 모든 페이지가 show_in_menu = true인지 확인
    foreach ($response['data'] as $page) {
        $this->assertDatabaseHas('pages', [
            'id' => $page['id'],
            'show_in_menu' => true
        ]);
    }
});

test('관리자가_페이지_생성_성공', function () {
    // Given: 관리자 사용자 생성 및 로그인
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');
    
    $category = Category::factory()->create();

    $pageData = [
        'title' => '새로운 페이지',
        'content' => '새로 생성된 페이지의 내용입니다.',
        'excerpt' => '새 페이지 요약',
        'template' => 'full-width',
        'is_published' => true,
        'show_in_menu' => true,
        'menu_title' => '메뉴 제목',
        'sort_order' => 5,
        'category_id' => $category->id,
        'meta_title' => 'SEO 제목',
        'meta_description' => 'SEO 설명'
    ];

    // When: 페이지 생성 API 호출
    $response = $this->postJson('/api/v1/pages', $pageData);

    // Then: 생성 성공 응답 및 데이터베이스 확인
    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'title' => '새로운 페이지',
                'content' => '새로 생성된 페이지의 내용입니다.',
                'template' => 'full-width',
                'is_published' => true,
                'show_in_menu' => true,
                'menu_title' => '메뉴 제목'
            ]
        ]);

    $this->assertDatabaseHas('pages', [
        'title' => '새로운 페이지',
        'content' => '새로 생성된 페이지의 내용입니다.',
        'template' => 'full-width',
        'user_id' => $admin->id
    ]);
});

test('일반_사용자가_페이지_생성시_권한_없음_에러', function () {
    // Given: 일반 사용자 생성 및 로그인
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user, 'api');

    $pageData = [
        'title' => '새로운 페이지',
        'content' => '새로 생성된 페이지의 내용입니다.'
    ];

    // When: 페이지 생성 API 호출
    $response = $this->postJson('/api/v1/pages', $pageData);

    // Then: 권한 없음 에러 응답 확인
    $response->assertStatus(403);
});

test('관리자가_페이지_수정_성공', function () {
    // Given: 관리자 사용자 및 페이지 생성
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');
    
    $page = Page::factory()->create([
        'title' => '원래 페이지',
        'content' => '원래 내용',
        'template' => 'default'
    ]);

    $updateData = [
        'title' => '수정된 페이지',
        'content' => '수정된 내용',
        'template' => 'landing',
        'is_published' => false,
        'show_in_menu' => false
    ];

    // When: 페이지 수정 API 호출
    $response = $this->putJson("/api/v1/pages/{$page->id}", $updateData);

    // Then: 수정 성공 응답 및 데이터베이스 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'title' => '수정된 페이지',
                'content' => '수정된 내용',
                'template' => 'landing',
                'is_published' => false,
                'show_in_menu' => false
            ]
        ]);

    $this->assertDatabaseHas('pages', [
        'id' => $page->id,
        'title' => '수정된 페이지',
        'content' => '수정된 내용',
        'template' => 'landing'
    ]);
});

test('관리자가_페이지_삭제_성공', function () {
    // Given: 관리자 사용자 및 페이지 생성
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');
    
    $page = Page::factory()->create();

    // When: 페이지 삭제 API 호출
    $response = $this->deleteJson("/api/v1/pages/{$page->id}");

    // Then: 삭제 성공 응답 및 데이터베이스 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true
        ]);

    $this->assertDatabaseMissing('pages', [
        'id' => $page->id
    ]);
});

test('페이지_계층구조_생성_및_조회_성공', function () {
    // Given: 관리자 사용자 로그인
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');
    
    // 부모 페이지 생성
    $parentPage = Page::factory()->create([
        'title' => '부모 페이지',
        'parent_id' => null
    ]);
    
    // 자식 페이지 생성
    $childPageData = [
        'title' => '자식 페이지',
        'content' => '자식 페이지 내용',
        'parent_id' => $parentPage->id,
        'is_published' => true
    ];

    // When: 자식 페이지 생성 API 호출
    $response = $this->postJson('/api/v1/pages', $childPageData);

    // Then: 생성 성공 및 계층구조 확인
    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'title' => '자식 페이지',
                'parent_id' => $parentPage->id
            ]
        ]);

    // 자식 페이지 상세 조회로 부모 정보 확인
    $childPage = $response['data'];
    $detailResponse = $this->getJson("/api/v1/pages/{$childPage['slug']}");
    
    $detailResponse->assertStatus(200);
    expect($detailResponse['data']['parent_id'])->toBe($parentPage->id);
});

test('페이지_템플릿_정보_포함_확인', function () {
    // Given: 특정 템플릿을 가진 페이지 생성
    $page = Page::factory()->create([
        'template' => 'landing'
    ]);

    // When: 페이지 상세 조회
    $response = $this->getJson("/api/v1/pages/{$page->slug}");

    // Then: 템플릿 정보가 포함되어 있는지 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'template',
                'meta' => [
                    'template_info' => [
                        'name',
                        'description'
                    ]
                ]
            ]
        ]);
    
    expect($response['data']['template'])->toBe('landing');
    expect($response['data']['meta']['template_info']['name'])->toBe('랜딩 페이지');
});

test('페이지_읽기시간_계산_확인', function () {
    // Given: 긴 내용을 가진 페이지 생성 (약 400단어)
    $longContent = str_repeat('This is a test content with multiple words. ', 50);
    $page = Page::factory()->create([
        'content' => $longContent
    ]);

    // When: 페이지 상세 조회
    $response = $this->getJson("/api/v1/pages/{$page->slug}");

    // Then: 읽기 시간이 계산되어 있는지 확인 (분당 200단어 기준, 약 2분)
    $response->assertStatus(200);
    expect($response['data']['reading_time'])->toBeGreaterThan(0);
    expect($response['data']['meta']['word_count'])->toBeGreaterThan(300);
});

test('페이지_유효성_검사_실패', function () {
    // Given: 관리자 사용자 로그인
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');

    $invalidData = [
        'title' => '', // 필수값 누락
        'template' => 'invalid_template' // 잘못된 템플릿
    ];

    // When: 잘못된 데이터로 페이지 생성 시도
    $response = $this->postJson('/api/v1/pages', $invalidData);

    // Then: 유효성 검사 실패 응답 확인
    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR'
            ]
        ]);
});

test('미발행_페이지는_목록에서_제외', function () {
    // Given: 발행된 페이지와 미발행된 페이지 생성
    Page::factory()->count(2)->create(['is_published' => true]);
    Page::factory()->count(3)->create(['is_published' => false]);

    // When: 공개 페이지 목록 조회
    $response = $this->getJson('/api/v1/pages');

    // Then: 발행된 페이지만 반환되는지 확인
    $response->assertStatus(200);
    expect($response['data'])->toHaveCount(2);
    
    foreach ($response['data'] as $page) {
        expect($page['is_published'])->toBe(true);
    }
});