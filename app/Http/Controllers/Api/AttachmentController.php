<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 첨부파일 관리 컨트롤러
 * 
 * @OA\Tag(
 *     name="Attachments",
 *     description="첨부파일 관리 API"
 * )
 */
class AttachmentController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/posts/{post}/attachments",
     *     summary="포스트 첨부파일 목록 조회",
     *     description="포스트의 첨부파일 목록을 조회합니다",
     *     tags={"Attachments"},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="첨부파일 타입 필터",
     *         required=false,
     *         @OA\Schema(type="string", enum={"image", "document", "video", "audio", "archive", "code", "file"})
     *     ),
     *     @OA\Parameter(
     *         name="public_only",
     *         in="query",
     *         description="공개 파일만 조회",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="첨부파일 목록 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="첨부파일 목록을 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="filename", type="string", example="document.pdf"),
     *                     @OA\Property(property="original_name", type="string", example="원본파일명.pdf"),
     *                     @OA\Property(property="url", type="string", example="/storage/attachments/document.pdf"),
     *                     @OA\Property(property="size", type="integer", example=1024000),
     *                     @OA\Property(property="formatted_size", type="string", example="1.0 MB"),
     *                     @OA\Property(property="type", type="string", example="document"),
     *                     @OA\Property(property="icon", type="string", example="fas fa-file-alt"),
     *                     @OA\Property(property="description", type="string", example="문서 설명"),
     *                     @OA\Property(property="download_count", type="integer", example=5),
     *                     @OA\Property(property="is_public", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="포스트를 찾을 수 없음"
     *     )
     * )
     */
    public function index(Request $request, Post $post): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:image,document,video,audio,archive,code,file',
            'public_only' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = $post->attachments()->ordered();

        if ($request->type) {
            $query->byType($request->type);
        }

        if ($request->boolean('public_only')) {
            $query->public();
        }

        $attachments = $query->get();

        return $this->successResponse($attachments->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'original_name' => $attachment->original_name,
                'url' => $attachment->url,
                'full_url' => $attachment->full_url,
                'size' => $attachment->size,
                'formatted_size' => $attachment->formatted_size,
                'mime_type' => $attachment->mime_type,
                'type' => $attachment->type,
                'icon' => $attachment->icon,
                'description' => $attachment->description,
                'download_count' => $attachment->download_count,
                'sort_order' => $attachment->sort_order,
                'is_public' => $attachment->is_public,
                'created_at' => $attachment->created_at,
                'updated_at' => $attachment->updated_at,
            ];
        }), '첨부파일 목록을 조회했습니다');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/posts/{post}/attachments",
     *     summary="첨부파일 업로드",
     *     description="포스트에 첨부파일을 업로드합니다",
     *     tags={"Attachments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="업로드할 파일"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     description="파일 설명"
     *                 ),
     *                 @OA\Property(
     *                     property="sort_order",
     *                     type="integer",
     *                     description="정렬 순서"
     *                 ),
     *                 @OA\Property(
     *                     property="is_public",
     *                     type="boolean",
     *                     description="공개 여부",
     *                     default=true
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="첨부파일 업로드 성공"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="유효성 검사 실패"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="권한 없음"
     *     )
     * )
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        if (!auth()->user()->isAdmin() && $post->user_id !== auth()->id()) {
            return $this->forbiddenResponse('첨부파일을 업로드할 권한이 없습니다');
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:20480', // 최대 20MB
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_public' => 'boolean',
        ], [
            'file.required' => '파일을 선택해주세요',
            'file.max' => '파일 크기는 20MB를 초과할 수 없습니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            // 파일 저장
            $path = $file->storeAs('attachments/' . $post->id, $filename, 'public');

            // 첨부파일 레코드 생성
            $attachment = PostAttachment::create([
                'post_id' => $post->id,
                'filename' => $filename,
                'original_name' => $originalName,
                'path' => $path,
                'mime_type' => $mimeType,
                'size' => $size,
                'type' => PostAttachment::determineTypeFromMimeType($mimeType),
                'description' => $request->description,
                'sort_order' => $request->sort_order ?? 0,
                'is_public' => $request->boolean('is_public', true),
            ]);

            return $this->createdResponse([
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'original_name' => $attachment->original_name,
                'url' => $attachment->url,
                'full_url' => $attachment->full_url,
                'size' => $attachment->size,
                'formatted_size' => $attachment->formatted_size,
                'mime_type' => $attachment->mime_type,
                'type' => $attachment->type,
                'icon' => $attachment->icon,
                'description' => $attachment->description,
                'download_count' => $attachment->download_count,
                'sort_order' => $attachment->sort_order,
                'is_public' => $attachment->is_public,
            ], '첨부파일이 업로드되었습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse('첨부파일 업로드 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attachments/{attachment}",
     *     summary="첨부파일 상세 조회",
     *     description="첨부파일의 상세 정보를 조회합니다",
     *     tags={"Attachments"},
     *     @OA\Parameter(
     *         name="attachment",
     *         in="path",
     *         description="첨부파일 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="첨부파일 정보 조회 성공"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="첨부파일을 찾을 수 없음"
     *     )
     * )
     */
    public function show(PostAttachment $attachment): JsonResponse
    {
        if (!$attachment->is_public) {
            $user = auth()->user();
            if (!$user || (!$user->isAdmin() && $attachment->post->user_id !== $user->id)) {
                return $this->forbiddenResponse('비공개 첨부파일에 접근할 권한이 없습니다');
            }
        }

        return $this->successResponse([
            'id' => $attachment->id,
            'filename' => $attachment->filename,
            'original_name' => $attachment->original_name,
            'url' => $attachment->url,
            'full_url' => $attachment->full_url,
            'size' => $attachment->size,
            'formatted_size' => $attachment->formatted_size,
            'mime_type' => $attachment->mime_type,
            'type' => $attachment->type,
            'icon' => $attachment->icon,
            'description' => $attachment->description,
            'download_count' => $attachment->download_count,
            'sort_order' => $attachment->sort_order,
            'is_public' => $attachment->is_public,
            'post' => [
                'id' => $attachment->post->id,
                'title' => $attachment->post->title,
                'slug' => $attachment->post->slug,
            ],
            'created_at' => $attachment->created_at,
            'updated_at' => $attachment->updated_at,
        ], '첨부파일 정보를 조회했습니다');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/attachments/{attachment}",
     *     summary="첨부파일 정보 수정",
     *     description="첨부파일의 정보를 수정합니다",
     *     tags={"Attachments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="attachment",
     *         in="path",
     *         description="첨부파일 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="description", type="string", description="파일 설명"),
     *             @OA\Property(property="sort_order", type="integer", description="정렬 순서"),
     *             @OA\Property(property="is_public", type="boolean", description="공개 여부")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="첨부파일 정보 수정 성공"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="권한 없음"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="첨부파일을 찾을 수 없음"
     *     )
     * )
     */
    public function update(Request $request, PostAttachment $attachment): JsonResponse
    {
        if (!auth()->user()->isAdmin() && $attachment->post->user_id !== auth()->id()) {
            return $this->forbiddenResponse('첨부파일을 수정할 권한이 없습니다');
        }

        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_public' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $attachment->update(array_filter([
                'description' => $request->description,
                'sort_order' => $request->sort_order,
                'is_public' => $request->boolean('is_public'),
            ], function ($value) {
                return $value !== null;
            }));

            return $this->successResponse([
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'original_name' => $attachment->original_name,
                'url' => $attachment->url,
                'full_url' => $attachment->full_url,
                'size' => $attachment->size,
                'formatted_size' => $attachment->formatted_size,
                'mime_type' => $attachment->mime_type,
                'type' => $attachment->type,
                'icon' => $attachment->icon,
                'description' => $attachment->description,
                'download_count' => $attachment->download_count,
                'sort_order' => $attachment->sort_order,
                'is_public' => $attachment->is_public,
            ], '첨부파일 정보가 수정되었습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse('첨부파일 수정 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/attachments/{attachment}",
     *     summary="첨부파일 삭제",
     *     description="첨부파일을 삭제합니다",
     *     tags={"Attachments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="attachment",
     *         in="path",
     *         description="첨부파일 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="첨부파일 삭제 성공"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="권한 없음"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="첨부파일을 찾을 수 없음"
     *     )
     * )
     */
    public function destroy(PostAttachment $attachment): JsonResponse
    {
        if (!auth()->user()->isAdmin() && $attachment->post->user_id !== auth()->id()) {
            return $this->forbiddenResponse('첨부파일을 삭제할 권한이 없습니다');
        }

        try {
            $attachment->delete();

            return $this->deletedResponse('첨부파일이 삭제되었습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse('첨부파일 삭제 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attachments/{attachment}/download",
     *     summary="첨부파일 다운로드",
     *     description="첨부파일을 다운로드합니다",
     *     tags={"Attachments"},
     *     @OA\Parameter(
     *         name="attachment",
     *         in="path",
     *         description="첨부파일 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="파일 다운로드",
     *         @OA\MediaType(
     *             mediaType="application/octet-stream"
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="첨부파일을 찾을 수 없음"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="권한 없음"
     *     )
     * )
     */
    public function download(PostAttachment $attachment): StreamedResponse|JsonResponse
    {
        if (!$attachment->is_public) {
            $user = auth()->user();
            if (!$user || (!$user->isAdmin() && $attachment->post->user_id !== $user->id)) {
                return $this->forbiddenResponse('비공개 첨부파일에 접근할 권한이 없습니다');
            }
        }

        if (!Storage::disk('public')->exists($attachment->path)) {
            return $this->notFoundResponse('파일을 찾을 수 없습니다');
        }

        // 다운로드 카운트 증가
        $attachment->incrementDownloadCount();

        return Storage::disk('public')->download($attachment->path, $attachment->original_name);
    }
}