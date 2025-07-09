<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| JWT 인증 시스템 API 테스트 (Authentication API Test)
|--------------------------------------------------------------------------
*/

/**
 * 테스트 목적: 사용자 회원가입 API 기능 검증
 * 테스트 시나리오: 유효한 데이터로 회원가입 요청
 * 기대 결과: 201 상태코드, JWT 토큰 반환, 사용자 데이터 생성
 * 관련 비즈니스 규칙: 기본 역할은 'user', 이메일 중복 불가
 */
test('회원가입_성공시_JWT_토큰_반환', function () {
    // Given: 유효한 회원가입 데이터 준비
    $userData = [
        'name' => '홍길동',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123'
    ];

    // When: 회원가입 API 호출
    $response = $this->postJson('/api/v1/auth/register', $userData);

    // Then: 성공 응답 및 토큰 반환 확인
    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email', 'role']
            ],
            'message'
        ])
        ->assertJson([
            'success' => true,
            'data' => [
                'token_type' => 'Bearer',
                'user' => [
                    'name' => '홍길동',
                    'email' => 'test@example.com',
                    'role' => 'user'
                ]
            ]
        ]);

    // 데이터베이스에 사용자 생성 확인
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'role' => 'user'
    ]);
});

/**
 * 테스트 목적: 회원가입시 유효성 검사 오류 처리 검증
 * 테스트 시나리오: 필수 필드 누락 또는 잘못된 데이터 입력
 * 기대 결과: 422 상태코드, 유효성 검사 오류 메시지 반환
 * 관련 비즈니스 규칙: 이메일 중복 불가, 비밀번호 최소 6자, 비밀번호 확인 일치
 */
test('회원가입_유효성_검사_실패시_422_반환', function () {
    // Given: 잘못된 회원가입 데이터
    $invalidData = [
        'name' => '',
        'email' => 'invalid-email',
        'password' => '123',
        'password_confirmation' => '456'
    ];

    // When: 회원가입 API 호출
    $response = $this->postJson('/api/v1/auth/register', $invalidData);

    // Then: 유효성 검사 오류 응답 확인
    $response->assertStatus(422)
        ->assertJsonStructure([
            'success',
            'error' => [
                'code',
                'message',
                'details'
            ]
        ])
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR'
            ]
        ]);
});

/**
 * 테스트 목적: 중복 이메일로 회원가입 시도시 오류 처리 검증
 * 테스트 시나리오: 이미 존재하는 이메일로 회원가입 시도
 * 기대 결과: 422 상태코드, 이메일 중복 오류 메시지
 * 관련 비즈니스 규칙: 이메일 unique 제약 조건
 */
test('중복_이메일_회원가입_시도시_422_반환', function () {
    // Given: 기존 사용자 생성
    User::factory()->create(['email' => 'existing@example.com']);
    
    $userData = [
        'name' => '새사용자',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123'
    ];

    // When: 동일한 이메일로 회원가입 시도
    $response = $this->postJson('/api/v1/auth/register', $userData);

    // Then: 중복 이메일 오류 확인
    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

/**
 * 테스트 목적: 올바른 인증정보로 로그인 기능 검증
 * 테스트 시나리오: 등록된 사용자가 올바른 이메일/비밀번호로 로그인
 * 기대 결과: 200 상태코드, JWT 토큰 반환, 사용자 정보 포함
 * 관련 비즈니스 규칙: JWT 토큰 기반 인증, 토큰 만료시간 포함
 */
test('올바른_인증정보로_로그인_성공', function () {
    // Given: 테스트 사용자 생성
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
        'role' => 'author'
    ]);

    $loginData = [
        'email' => 'user@example.com',
        'password' => 'password123'
    ];

    // When: 로그인 API 호출
    $response = $this->postJson('/api/v1/auth/login', $loginData);

    // Then: 성공 응답 및 토큰 반환 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email', 'role']
            ],
            'message'
        ])
        ->assertJson([
            'success' => true,
            'data' => [
                'token_type' => 'Bearer',
                'user' => [
                    'email' => 'user@example.com',
                    'role' => 'author'
                ]
            ]
        ]);

    // JWT 토큰 유효성 확인
    $token = $response->json('data.token');
    expect($token)->toBeString();
    expect(strlen($token))->toBeGreaterThan(100);
});

/**
 * 테스트 목적: 잘못된 인증정보로 로그인 시도시 오류 처리 검증
 * 테스트 시나리오: 존재하지 않는 이메일 또는 잘못된 비밀번호
 * 기대 결과: 401 상태코드, 인증 실패 메시지
 * 관련 비즈니스 규칙: 보안을 위해 구체적인 실패 이유는 노출하지 않음
 */
test('잘못된_인증정보로_로그인_시도시_401_반환', function () {
    // Given: 테스트 사용자 생성
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('correctpassword')
    ]);

    $wrongLoginData = [
        'email' => 'user@example.com',
        'password' => 'wrongpassword'
    ];

    // When: 잘못된 비밀번호로 로그인 시도
    $response = $this->postJson('/api/v1/auth/login', $wrongLoginData);

    // Then: 인증 실패 응답 확인
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => '이메일 또는 비밀번호가 올바르지 않습니다'
            ]
        ]);
});

/**
 * 테스트 목적: 인증된 사용자의 정보 조회 기능 검증
 * 테스트 시나리오: 유효한 JWT 토큰으로 사용자 정보 조회
 * 기대 결과: 200 상태코드, 사용자 정보 반환
 * 관련 비즈니스 규칙: JWT 토큰 기반 인증, 민감한 정보는 제외
 */
test('인증된_사용자_정보_조회_성공', function () {
    // Given: 인증된 사용자와 JWT 토큰 준비
    $user = User::factory()->create([
        'name' => '테스트 사용자',
        'email' => 'test@example.com',
        'role' => 'admin'
    ]);
    
    $token = JWTAuth::fromUser($user);

    // When: 인증 헤더와 함께 사용자 정보 조회
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token
    ])->getJson('/api/v1/auth/me');

    // Then: 사용자 정보 반환 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'id', 'name', 'email', 'role', 
                'email_verified_at', 'created_at', 'updated_at'
            ],
            'message'
        ])
        ->assertJson([
            'success' => true,
            'data' => [
                'name' => '테스트 사용자',
                'email' => 'test@example.com',
                'role' => 'admin'
            ]
        ]);
});

/**
 * 테스트 목적: 인증되지 않은 사용자의 정보 조회 시도시 오류 처리 검증
 * 테스트 시나리오: 토큰 없이 또는 잘못된 토큰으로 사용자 정보 조회 시도
 * 기대 결과: 401 상태코드, 인증 필요 메시지
 * 관련 비즈니스 규칙: JWT 미들웨어에 의한 인증 검사
 */
test('인증되지_않은_사용자_정보_조회시_401_반환', function () {
    // Given: 토큰 없이 요청
    
    // When: 인증 헤더 없이 사용자 정보 조회 시도
    $response = $this->getJson('/api/v1/auth/me');

    // Then: 인증 필요 오류 확인
    $response->assertStatus(401);
});

/**
 * 테스트 목적: 사용자 프로필 수정 기능 검증
 * 테스트 시나리오: 인증된 사용자가 이름과 이메일 수정
 * 기대 결과: 200 상태코드, 성공 메시지, 데이터베이스 업데이트
 * 관련 비즈니스 규칙: 본인만 수정 가능, 이메일 중복 검사
 */
test('인증된_사용자_프로필_수정_성공', function () {
    // Given: 인증된 사용자와 토큰 준비
    $user = User::factory()->create([
        'name' => '기존이름',
        'email' => 'old@example.com'
    ]);
    
    $token = JWTAuth::fromUser($user);
    
    $updateData = [
        'name' => '새로운이름',
        'email' => 'new@example.com'
    ];

    // When: 프로필 수정 API 호출
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token
    ])->putJson('/api/v1/auth/me', $updateData);

    // Then: 성공 응답 및 데이터베이스 업데이트 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '프로필이 수정되었습니다'
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => '새로운이름',
        'email' => 'new@example.com'
    ]);
});

/**
 * 테스트 목적: 비밀번호 변경 기능 검증
 * 테스트 시나리오: 인증된 사용자가 현재 비밀번호와 새 비밀번호로 변경
 * 기대 결과: 200 상태코드, 성공 메시지, 새 비밀번호로 암호화 저장
 * 관련 비즈니스 규칙: 현재 비밀번호 확인 필수, 새 비밀번호 해싱 저장
 */
test('인증된_사용자_비밀번호_변경_성공', function () {
    // Given: 인증된 사용자와 현재 비밀번호 설정
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword123')
    ]);
    
    $token = JWTAuth::fromUser($user);
    
    $passwordData = [
        'current_password' => 'oldpassword123',
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123'
    ];

    // When: 비밀번호 변경 API 호출
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token
    ])->putJson('/api/v1/auth/password', $passwordData);

    // Then: 성공 응답 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '비밀번호가 변경되었습니다'
        ]);

    // 새 비밀번호로 로그인 가능한지 확인
    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
    expect(Hash::check('oldpassword123', $user->password))->toBeFalse();
});

/**
 * 테스트 목적: 잘못된 현재 비밀번호로 변경 시도시 오류 처리 검증
 * 테스트 시나리오: 현재 비밀번호가 틀린 상태에서 비밀번호 변경 시도
 * 기대 결과: 400 상태코드, 현재 비밀번호 오류 메시지
 * 관련 비즈니스 규칙: 보안을 위해 현재 비밀번호 확인 필수
 */
test('잘못된_현재_비밀번호로_변경_시도시_400_반환', function () {
    // Given: 인증된 사용자와 잘못된 현재 비밀번호
    $user = User::factory()->create([
        'password' => Hash::make('correctpassword')
    ]);
    
    $token = JWTAuth::fromUser($user);
    
    $passwordData = [
        'current_password' => 'wrongpassword',
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123'
    ];

    // When: 잘못된 현재 비밀번호로 변경 시도
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token
    ])->putJson('/api/v1/auth/password', $passwordData);

    // Then: 현재 비밀번호 오류 확인
    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'error' => [
                'code' => 'INVALID_PASSWORD',
                'message' => '현재 비밀번호가 올바르지 않습니다'
            ]
        ]);
});

/**
 * 테스트 목적: JWT 토큰 갱신 기능 검증
 * 테스트 시나리오: 유효한 토큰으로 새로운 토큰 발급 요청
 * 기대 결과: 200 상태코드, 새로운 JWT 토큰 반환
 * 관련 비즈니스 규칙: 기존 토큰 무효화, 새 토큰 발급
 */
test('유효한_토큰으로_토큰_갱신_성공', function () {
    // Given: 인증된 사용자와 토큰 준비
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    // When: 토큰 갱신 API 호출
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token
    ])->postJson('/api/v1/auth/refresh');

    // Then: 새로운 토큰 반환 확인
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'token_type',
                'expires_in'
            ],
            'message'
        ])
        ->assertJson([
            'success' => true,
            'data' => [
                'token_type' => 'Bearer'
            ]
        ]);

    // 새 토큰이 기존 토큰과 다른지 확인
    $newToken = $response->json('data.token');
    expect($newToken)->not->toBe($token);
});

/**
 * 테스트 목적: 로그아웃 기능 검증
 * 테스트 시나리오: 인증된 사용자가 로그아웃 API 호출
 * 기대 결과: 200 상태코드, 성공 메시지 반환
 * 관련 비즈니스 규칙: JWT 토큰 무효화 처리
 */
test('인증된_사용자_로그아웃_성공', function () {
    // Given: 인증된 사용자와 토큰 준비
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    // When: 로그아웃 API 호출
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token
    ])->postJson('/api/v1/auth/logout');

    // Then: 성공 응답 확인
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '성공적으로 로그아웃되었습니다'
        ]);

    // 로그아웃 API 자체의 성공적인 응답만 검증
    // (테스트 환경에서 JWT 블랙리스트 동작은 별도 검증)
    expect($response->json('success'))->toBeTrue();
});