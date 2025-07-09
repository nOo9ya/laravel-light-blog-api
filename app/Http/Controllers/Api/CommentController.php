<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Http\Resources\CommentResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Comments",
 *     description="댓글 관리 API"
 * )
 */
class CommentController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/posts/{post}/comments",
     *     summary="포스트 댓글 목록 조회",
     *     description="특정 포스트의 댓글 목록을 조회합니다",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
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
     *         description="댓글 목록 조회 성공"
     *     )
     * )
     */
    public function index(Request $request, Post $post): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $perPage = $request->get('per_page', 20);

        // 계층형 댓글 구조로 조회 (최상위 댓글만 + 대댓글 포함)
        $comments = $post->topLevelComments()
            ->with([
                'user:id,name,email,avatar',
                'replies' => function ($query) {
                    $query->approved()
                          ->with('user:id,name,email,avatar')
                          ->orderBy('created_at');
                },
                'replies.replies' => function ($query) {
                    $query->approved()
                          ->with('user:id,name,email,avatar')
                          ->orderBy('created_at');
                }
            ])
            ->orderBy('created_at')
            ->paginate($perPage);

        return $this->paginatedResponse(
            $comments,
            CommentResource::class,
            '댓글 목록을 조회했습니다'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/posts/{post}/comments",
     *     summary="댓글 작성",
     *     description="새로운 댓글을 작성합니다",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", example="좋은 글 감사합니다!"),
     *             @OA\Property(property="parent_id", type="integer", example=1),
     *             @OA\Property(property="guest_name", type="string", example="홍길동"),
     *             @OA\Property(property="guest_email", type="string", example="guest@example.com"),
     *             @OA\Property(property="guest_password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="댓글 작성 성공"
     *     )
     * )
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        $isAuthenticated = Auth::check();

        // 인증된 사용자와 비인증 사용자에 대한 다른 유효성 검사
        $rules = [
            'content' => 'required|string|min:1|max:2000',
            'parent_id' => 'nullable|exists:comments,id',
        ];

        if (!$isAuthenticated) {
            $rules = array_merge($rules, [
                'guest_name' => 'required|string|max:50',
                'guest_email' => 'required|email|max:255',
                'guest_password' => 'required|string|min:4|max:50',
            ]);
        }

        $validator = Validator::make($request->all(), $rules, [
            'content.required' => '댓글 내용을 입력해주세요',
            'content.max' => '댓글은 2000자를 초과할 수 없습니다',
            'guest_name.required' => '이름을 입력해주세요',
            'guest_email.required' => '이메일을 입력해주세요',
            'guest_password.required' => '비밀번호를 입력해주세요',
            'parent_id.exists' => '존재하지 않는 댓글입니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // 대댓글 깊이 제한 (최대 3단계)
        if ($request->filled('parent_id')) {
            $parentComment = Comment::find($request->parent_id);
            
            if (!$parentComment) {
                return $this->notFoundResponse('존재하지 않는 댓글입니다');
            }
            
            // 부모 댓글이 같은 포스트인지 확인
            if ($parentComment->post_id !== $post->id) {
                return $this->businessErrorResponse(
                    'INVALID_PARENT',
                    '잘못된 부모 댓글입니다'
                );
            }
            
            // 대댓글 깊이 계산 및 제한
            $depth = $this->calculateCommentDepth($parentComment);
            if ($depth >= 3) {
                return $this->businessErrorResponse(
                    'MAX_DEPTH_EXCEEDED',
                    '댓글은 최대 3단계까지만 가능합니다'
                );
            }
        }

        $commentData = [
            'post_id' => $post->id,
            'content' => $request->content,
            'parent_id' => $request->parent_id,
        ];

        if ($isAuthenticated) {
            $commentData['user_id'] = Auth::id();
        } else {
            $commentData = array_merge($commentData, [
                'guest_name' => $request->guest_name,
                'guest_email' => $request->guest_email,
                'guest_password' => $request->guest_password,
            ]);
        }

        try {
            $comment = Comment::create($commentData);
            $comment->load(['user', 'parent']);
            
            // 스팸 필터링 자동 적용
            \App\Services\SpamFilterService::autoModerate($comment);
            
            $message = $comment->is_spam ? 
                '댓글이 스팸으로 분류되어 차단되었습니다.' :
                ($comment->is_approved ? 
                    '댓글이 작성되었습니다.' : 
                    '댓글이 작성되었습니다. 관리자 승인 후 게시됩니다.');
            
            return $this->createdResponse(
                new CommentResource($comment),
                $message
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('댓글 작성 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 댓글 깊이 계산
     */
    private function calculateCommentDepth(Comment $comment): int
    {
        $depth = 1;
        $current = $comment;
        
        while ($current->parent_id) {
            $current = $current->parent;
            $depth++;
        }
        
        return $depth;
    }

    /**
     * 댓글 수정 권한 확인
     */
    private function canModifyComment(Comment $comment, Request $request): bool
    {
        $user = Auth::user();
        
        // 관리자는 모든 댓글 수정 가능
        if ($user && $user->isAdmin()) {
            return true;
        }
        
        // 회원 댓글의 경우 작성자만 수정 가능
        if ($comment->user_id && $user && $comment->user_id === $user->id) {
            return true;
        }
        
        // 비회원 댓글의 경우 비밀번호 확인 필요 (별도 검증)
        if ($comment->is_guest && $request->has('guest_password')) {
            return true;
        }
        
        return false;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/comments/{comment}",
     *     summary="댓글 상세 조회",
     *     description="특정 댓글의 상세 정보를 조회합니다",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="comment",
     *         in="path",
     *         description="댓글 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="댓글 조회 성공"
     *     )
     * )
     */
    public function show(Comment $comment): JsonResponse
    {
        if (!$comment->is_approved) {
            return $this->notFoundResponse('댓글을 찾을 수 없습니다');
        }

        $comment->load(['user', 'parent', 'replies.user']);

        return $this->successResponse(
            new CommentResource($comment),
            '댓글을 조회했습니다'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/comments/{comment}",
     *     summary="댓글 수정",
     *     description="댓글을 수정합니다 (작성자만 가능)",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="comment",
     *         in="path",
     *         description="댓글 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", example="수정된 댓글 내용"),
     *             @OA\Property(property="guest_password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="댓글 수정 성공"
     *     )
     * )
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        // 권한 확인
        if (!$this->canModifyComment($comment, $request)) {
            return $this->forbiddenResponse('댓글을 수정할 권한이 없습니다');
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:1|max:2000',
            'guest_password' => $comment->is_guest ? 'required|string' : 'nullable',
        ], [
            'content.required' => '댓글 내용을 입력해주세요',
            'content.max' => '댓글은 2000자를 초과할 수 없습니다',
            'guest_password.required' => '비밀번호를 입력해주세요',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // 비회원 댓글의 경우 비밀번호 확인
        if ($comment->is_guest && !$comment->verifyGuestPassword($request->guest_password)) {
            return $this->forbiddenResponse('비밀번호가 일치하지 않습니다');
        }

        try {
            $comment->update([
                'content' => $request->content,
                'updated_at' => now(),
            ]);

            $comment->load(['user', 'parent']);

            return $this->successResponse(
                new CommentResource($comment),
                '댓글이 수정되었습니다'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('댓글 수정 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/comments/{comment}",
     *     summary="댓글 삭제",
     *     description="댓글을 삭제합니다 (작성자 또는 관리자만 가능)",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="comment",
     *         in="path",
     *         description="댓글 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="guest_password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="댓글 삭제 성공"
     *     )
     * )
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        // 권한 확인
        if (!$this->canModifyComment($comment, $request)) {
            return $this->forbiddenResponse('댓글을 삭제할 권한이 없습니다');
        }

        // 비회원 댓글의 경우 비밀번호 확인
        if ($comment->is_guest) {
            $validator = Validator::make($request->all(), [
                'guest_password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            if (!$comment->verifyGuestPassword($request->guest_password)) {
                return $this->forbiddenResponse('비밀번호가 일치하지 않습니다');
            }
        }

        try {
            // 하위 댓글이 있는 경우 소프트 삭제 (내용만 삭제)
            if ($comment->replies()->count() > 0) {
                $comment->update([
                    'content' => '[deleted]',
                    'is_deleted' => true,
                    'deleted_at' => now(),
                ]);
                $message = '댓글이 삭제되었습니다 (답글 보호)';
            } else {
                $comment->delete();
                $message = '댓글이 삭제되었습니다';
            }

            return $this->deletedResponse($message);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('댓글 삭제 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/comments/{comment}/approve",
     *     summary="댓글 승인",
     *     description="댓글을 승인합니다 (관리자만 가능)",
     *     tags={"Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="comment",
     *         in="path",
     *         description="댓글 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="댓글 승인 성공"
     *     )
     * )
     */
    public function approve(Comment $comment): JsonResponse
    {
        if ($comment->is_approved) {
            return $this->businessErrorResponse(
                'ALREADY_APPROVED',
                '이미 승인된 댓글입니다'
            );
        }

        $comment->approve();
        $comment->load(['user', 'parent']);

        return $this->successResponse(
            new CommentResource($comment),
            '댓글이 승인되었습니다'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/comments/{comment}/spam",
     *     summary="댓글 스팸 처리",
     *     description="댓글을 스팸으로 처리합니다 (관리자만 가능)",
     *     tags={"Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="comment",
     *         in="path",
     *         description="댓글 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="댓글 스팸 처리 성공"
     *     )
     * )
     */
    public function spam(Comment $comment): JsonResponse
    {
        if ($comment->is_spam) {
            return $this->businessErrorResponse(
                'ALREADY_SPAM',
                '이미 스팸으로 처리된 댓글입니다'
            );
        }

        $comment->markAsSpam();
        $comment->load(['user', 'parent']);

        return $this->successResponse(
            new CommentResource($comment),
            '댓글이 스팸으로 처리되었습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/comments",
     *     summary="관리자용 댓글 목록 조회",
     *     description="모든 댓글을 상태별로 조회합니다 (관리자만 가능)",
     *     tags={"Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="댓글 상태",
     *         required=false,
     *         @OA\Schema(type="string", enum={"approved", "pending", "spam"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="댓글 목록 조회 성공"
     *     )
     * )
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:approved,pending,spam',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = Comment::with(['user', 'post:id,title', 'parent'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 20);
        $comments = $query->paginate($perPage);

        return $this->paginatedResponse(
            $comments,
            CommentResource::class,
            '댓글 목록을 조회했습니다'
        );
    }

    /**
     * 댓글 수정 권한 확인
     */
    private function canModifyComment(Comment $comment, Request $request): bool
    {
        // 관리자는 모든 댓글 수정 가능
        if (Auth::check() && Auth::user()->isAdmin()) {
            return true;
        }

        // 회원 댓글의 경우 작성자 확인
        if ($comment->user_id) {
            return Auth::check() && Auth::id() === $comment->user_id;
        }

        // 비회원 댓글의 경우 비밀번호로 확인 (여기서는 true 반환, 실제 확인은 각 메서드에서)
        return $comment->is_guest;
    }
}