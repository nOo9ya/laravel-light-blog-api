<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="사용자 관리 API (관리자 전용)"
 * )
 */
class UserController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/admin/users",
     *     summary="사용자 목록 조회",
     *     description="모든 사용자 목록을 조회합니다 (관리자만 가능)",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="역할 필터 (admin, author, user)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"admin", "author", "user"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="이름 또는 이메일 검색",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="페이지 번호",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="페이지당 아이템 수",
     *         required=false,
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="사용자 목록 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="사용자 목록을 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/User")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'nullable|in:admin,author,user',
            'search' => 'nullable|string|max:255',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = User::query()
            ->withCount(['posts', 'comments', 'pages'])
            ->latest();

        // 역할 필터링
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // 검색 필터링
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $users = $query->paginate($perPage);

        return $this->paginatedResponse(
            $users,
            UserResource::class,
            '사용자 목록을 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/users/{id}",
     *     summary="사용자 상세 조회",
     *     description="특정 사용자의 상세 정보를 조회합니다 (관리자만 가능)",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="사용자 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="사용자 조회 성공"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="사용자를 찾을 수 없음"
     *     )
     * )
     */
    public function show(User $user): JsonResponse
    {
        $user->loadCount(['posts', 'comments', 'pages']);

        return $this->successResponse(
            new UserResource($user),
            '사용자 정보를 조회했습니다'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/users",
     *     summary="사용자 생성",
     *     description="새로운 사용자를 생성합니다 (관리자만 가능)",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "role"},
     *             @OA\Property(property="name", type="string", example="홍길동"),
     *             @OA\Property(property="email", type="string", example="hong@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123"),
     *             @OA\Property(property="role", type="string", enum={"admin", "author", "user"}, example="author")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="사용자 생성 성공"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,author,user',
        ], [
            'name.required' => '이름을 입력해주세요',
            'email.required' => '이메일을 입력해주세요',
            'email.unique' => '이미 사용 중인 이메일입니다',
            'password.required' => '비밀번호를 입력해주세요',
            'password.min' => '비밀번호는 최소 8자 이상이어야 합니다',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다',
            'role.required' => '역할을 선택해주세요',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return $this->createdResponse(
            new UserResource($user),
            '사용자가 생성되었습니다'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/users/{id}",
     *     summary="사용자 정보 수정",
     *     description="사용자 정보를 수정합니다 (관리자만 가능)",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="사용자 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "role"},
     *             @OA\Property(property="name", type="string", example="홍길동"),
     *             @OA\Property(property="email", type="string", example="hong@example.com"),
     *             @OA\Property(property="password", type="string", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", example="newpassword123"),
     *             @OA\Property(property="role", type="string", enum={"admin", "author", "user"}, example="author")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="사용자 수정 성공"
     *     )
     * )
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,author,user',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ];

        // 비밀번호가 제공된 경우에만 업데이트
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return $this->updatedResponse(
            new UserResource($user),
            '사용자 정보가 수정되었습니다'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/users/{id}",
     *     summary="사용자 삭제",
     *     description="사용자를 삭제합니다 (관리자만 가능)",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="사용자 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="사용자 삭제 성공"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="삭제할 수 없음 (본인 계정 또는 컨텐츠 존재)"
     *     )
     * )
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // 자기 자신을 삭제하는 것 방지
        if ($request->user()->id === $user->id) {
            return $this->businessErrorResponse(
                'CANNOT_DELETE_SELF',
                '본인 계정은 삭제할 수 없습니다'
            );
        }

        // 작성한 컨텐츠가 있는지 확인
        $hasContent = $user->posts()->count() > 0 || 
                     $user->comments()->count() > 0 || 
                     $user->pages()->count() > 0;

        if ($hasContent) {
            return $this->businessErrorResponse(
                'HAS_CONTENT',
                '작성한 컨텐츠가 있는 사용자는 삭제할 수 없습니다'
            );
        }

        $user->delete();

        return $this->deletedResponse('사용자가 삭제되었습니다');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/users/{id}/role",
     *     summary="사용자 역할 변경",
     *     description="사용자의 역할을 변경합니다 (관리자만 가능)",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="사용자 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"role"},
     *             @OA\Property(property="role", type="string", enum={"admin", "author", "user"}, example="author")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="역할 변경 성공"
     *     )
     * )
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:admin,author,user',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // 자기 자신의 관리자 권한을 제거하는 것 방지
        if ($request->user()->id === $user->id && 
            $request->user()->isAdmin() && 
            $request->role !== 'admin') {
            return $this->businessErrorResponse(
                'CANNOT_REMOVE_OWN_ADMIN',
                '본인의 관리자 권한은 제거할 수 없습니다'
            );
        }

        $oldRole = $user->role;
        $user->update(['role' => $request->role]);

        return $this->successResponse(
            new UserResource($user),
            "사용자 역할이 '{$oldRole}'에서 '{$request->role}'로 변경되었습니다"
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/users/stats",
     *     summary="사용자 통계",
     *     description="사용자 통계 정보를 조회합니다 (관리자만 가능)",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="사용자 통계 조회 성공"
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'by_role' => [
                'admin' => User::where('role', 'admin')->count(),
                'author' => User::where('role', 'author')->count(),
                'user' => User::where('role', 'user')->count(),
            ],
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'active_users' => [
                'with_posts' => User::has('posts')->count(),
                'with_comments' => User::has('comments')->count(),
            ]
        ];

        return $this->successResponse(
            $stats,
            '사용자 통계를 조회했습니다'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/users/{id}/verify-email",
     *     summary="이메일 인증 강제 처리",
     *     description="사용자의 이메일을 강제로 인증 처리합니다 (관리자만 가능)",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="사용자 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="이메일 인증 처리 성공"
     *     )
     * )
     */
    public function verifyEmail(User $user): JsonResponse
    {
        if ($user->hasVerifiedEmail()) {
            return $this->businessErrorResponse(
                'ALREADY_VERIFIED',
                '이미 이메일이 인증된 사용자입니다'
            );
        }

        $user->markEmailAsVerified();

        return $this->successResponse(
            new UserResource($user),
            '이메일이 인증 처리되었습니다'
        );
    }
}