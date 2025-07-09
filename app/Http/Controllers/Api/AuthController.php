<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="사용자 인증 관련 API"
 * )
 */
class AuthController extends Controller
{
    use ApiResponse;
    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="사용자 로그인",
     *     description="이메일과 비밀번호로 사용자 인증을 진행하고 JWT 토큰을 발급합니다",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="로그인 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Admin User"),
     *                     @OA\Property(property="email", type="string", example="admin@example.com"),
     *                     @OA\Property(property="role", type="string", example="admin")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="로그인에 성공했습니다")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="인증 실패",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="INVALID_CREDENTIALS"),
     *                 @OA\Property(property="message", type="string", example="이메일 또는 비밀번호가 올바르지 않습니다")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="유효성 검사 실패",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
     *                 @OA\Property(property="message", type="string", example="입력 데이터가 올바르지 않습니다"),
     *                 @OA\Property(
     *                     property="details",
     *                     type="object",
     *                     @OA\Property(
     *                         property="email",
     *                         type="array",
     *                         @OA\Items(type="string", example="이메일 형식이 올바르지 않습니다")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ], [
            'email.required' => '이메일을 입력해주세요',
            'email.email' => '올바른 이메일 형식을 입력해주세요',
            'password.required' => '비밀번호를 입력해주세요',
            'password.min' => '비밀번호는 최소 6자 이상이어야 합니다',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '입력 데이터가 올바르지 않습니다',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_CREDENTIALS',
                        'message' => '이메일 또는 비밀번호가 올바르지 않습니다'
                    ]
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_ERROR',
                    'message' => '토큰 생성에 실패했습니다'
                ]
            ], 500);
        }

        $user = Auth::guard('api')->user();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'email_verified_at' => $user->email_verified_at,
                ]
            ],
            'message' => '로그인에 성공했습니다'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="사용자 회원가입",
     *     description="새로운 사용자 계정을 생성합니다",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="홍길동"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="회원가입 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="홍길동"),
     *                     @OA\Property(property="email", type="string", example="user@example.com"),
     *                     @OA\Property(property="role", type="string", example="user")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="회원가입이 완료되었습니다")
     *         )
     *     )
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'name.required' => '이름을 입력해주세요',
            'email.required' => '이메일을 입력해주세요',
            'email.email' => '올바른 이메일 형식을 입력해주세요',
            'email.unique' => '이미 사용중인 이메일입니다',
            'password.required' => '비밀번호를 입력해주세요',
            'password.min' => '비밀번호는 최소 6자 이상이어야 합니다',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '입력 데이터가 올바르지 않습니다',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user', // 기본 역할은 일반 사용자
        ]);

        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_ERROR',
                    'message' => '토큰 생성에 실패했습니다'
                ]
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'email_verified_at' => $user->email_verified_at,
                ]
            ],
            'message' => '회원가입이 완료되었습니다'
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="로그아웃",
     *     description="현재 토큰을 무효화합니다",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="로그아웃 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="성공적으로 로그아웃되었습니다")
     *         )
     *     )
     * )
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return response()->json([
                'success' => true,
                'message' => '성공적으로 로그아웃되었습니다'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_ERROR',
                    'message' => '토큰 무효화에 실패했습니다'
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/refresh",
     *     summary="토큰 갱신",
     *     description="현재 토큰을 갱신하여 새로운 토큰을 발급합니다",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="토큰 갱신 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             ),
     *             @OA\Property(property="message", type="string", example="토큰이 갱신되었습니다")
     *         )
     *     )
     * )
     */
    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $newToken,
                    'token_type' => 'Bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 60
                ],
                'message' => '토큰이 갱신되었습니다'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_REFRESH_ERROR',
                    'message' => '토큰 갱신에 실패했습니다'
                ]
            ], 401);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     summary="사용자 정보 조회",
     *     description="현재 인증된 사용자의 정보를 조회합니다",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="사용자 정보 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="홍길동"),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="role", type="string", example="user"),
     *                 @OA\Property(property="email_verified_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
     *             ),
     *             @OA\Property(property="message", type="string", example="사용자 정보를 조회했습니다")
     *         )
     *     )
     * )
     */
    public function me(): JsonResponse
    {
        $user = Auth::guard('api')->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'message' => '사용자 정보를 조회했습니다'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/auth/me",
     *     summary="사용자 프로필 수정",
     *     description="현재 인증된 사용자의 프로필 정보를 수정합니다",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="홍길동"),
     *             @OA\Property(property="email", type="string", format="email", example="newemail@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="프로필 수정 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="프로필이 수정되었습니다")
     *         )
     *     )
     * )
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
        ], [
            'name.required' => '이름을 입력해주세요',
            'email.required' => '이메일을 입력해주세요',
            'email.email' => '올바른 이메일 형식을 입력해주세요',
            'email.unique' => '이미 사용중인 이메일입니다',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '입력 데이터가 올바르지 않습니다',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $user->update($request->only(['name', 'email']));

        return response()->json([
            'success' => true,
            'message' => '프로필이 수정되었습니다'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/auth/password",
     *     summary="비밀번호 변경",
     *     description="현재 인증된 사용자의 비밀번호를 변경합니다",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "new_password", "new_password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="oldpassword123"),
     *             @OA\Property(property="new_password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="비밀번호 변경 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="비밀번호가 변경되었습니다")
     *         )
     *     )
     * )
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => '현재 비밀번호를 입력해주세요',
            'new_password.required' => '새 비밀번호를 입력해주세요',
            'new_password.min' => '새 비밀번호는 최소 6자 이상이어야 합니다',
            'new_password.confirmed' => '새 비밀번호 확인이 일치하지 않습니다',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '입력 데이터가 올바르지 않습니다',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PASSWORD',
                    'message' => '현재 비밀번호가 올바르지 않습니다'
                ]
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => '비밀번호가 변경되었습니다'
        ]);
    }
}