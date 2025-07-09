<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Media",
 *     description="미디어 업로드 및 관리 API"
 * )
 */
class MediaController extends Controller
{
    use ApiResponse;

    private ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/media/upload",
     *     summary="파일 업로드",
     *     description="이미지 파일을 업로드합니다",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
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
     *                     description="업로드할 이미지 파일"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"post", "page", "avatar", "general"},
     *                     default="general",
     *                     description="업로드 유형"
     *                 ),
     *                 @OA\Property(
     *                     property="alt",
     *                     type="string",
     *                     description="대체 텍스트"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="파일 업로드 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="파일이 업로드되었습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="uuid-string"),
     *                 @OA\Property(property="filename", type="string", example="image.jpg"),
     *                 @OA\Property(property="original_name", type="string", example="original-image.jpg"),
     *                 @OA\Property(property="url", type="string", example="/storage/uploads/image.jpg"),
     *                 @OA\Property(property="size", type="integer", example=1024),
     *                 @OA\Property(property="mime_type", type="string", example="image/jpeg"),
     *                 @OA\Property(property="type", type="string", example="general"),
     *                 @OA\Property(property="alt", type="string", example="대체 텍스트")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="유효성 검사 실패"
     *     )
     * )
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|image|max:10240', // 최대 10MB
            'type' => 'nullable|string|in:post,page,avatar,general',
            'alt' => 'nullable|string|max:255',
        ], [
            'file.required' => '파일을 선택해주세요',
            'file.image' => '이미지 파일만 업로드 가능합니다',
            'file.max' => '파일 크기는 10MB를 초과할 수 없습니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $file = $request->file('file');
            $type = $request->get('type', 'general');
            $alt = $request->get('alt', '');

            // ImageService를 사용하여 업로드 처리
            $result = match($type) {
                'post' => $this->imageService->uploadMainImage($file, 'posts/main'),
                'page' => $this->imageService->uploadMainImage($file, 'pages/main'),
                'avatar' => $this->imageService->uploadMainImage($file, 'users/avatar'),
                default => $this->imageService->uploadContentImage($file, 'uploads/general')
            };

            // 메타데이터 준비
            $mediaData = [
                'id' => Str::uuid(),
                'filename' => basename($result['path']),
                'original_name' => $result['original_name'],
                'url' => Storage::url($result['path']),
                'full_url' => asset(Storage::url($result['path'])),
                'thumbnail_url' => isset($result['thumbnail_path']) ? asset(Storage::url($result['thumbnail_path'])) : null,
                'size' => $result['size'],
                'width' => $result['width'],
                'height' => $result['height'],
                'mime_type' => 'image/webp',
                'type' => $type,
                'alt' => $alt,
                'path' => $result['path'],
                'thumbnail_path' => $result['thumbnail_path'] ?? null,
                'uploaded_at' => now(),
                'uploaded_by' => auth()->id(),
            ];

            // 여기서 실제 프로젝트에서는 데이터베이스에 저장할 수 있습니다
            // Media::create($mediaData);

            return $this->createdResponse($mediaData, '파일이 업로드되고 WebP로 변환되었습니다');

        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse(['file' => [$e->getMessage()]]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'UPLOAD_FAILED',
                '파일 업로드 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/media",
     *     summary="미디어 파일 목록",
     *     description="업로드된 미디어 파일 목록을 조회합니다",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="미디어 타입 필터",
     *         required=false,
     *         @OA\Schema(type="string", enum={"post", "page", "avatar", "general"})
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
     *         description="미디어 목록 조회 성공"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:post,page,avatar,general',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // 실제 구현에서는 데이터베이스에서 조회
        // 현재는 임시로 빈 배열 반환
        $mediaFiles = collect([]);
        
        // 페이지네이션 시뮬레이션
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);
        
        $paginatedResult = new \Illuminate\Pagination\LengthAwarePaginator(
            $mediaFiles->slice(($page - 1) * $perPage, $perPage),
            $mediaFiles->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $this->paginatedResponse(
            $paginatedResult,
            'array', // 리소스 클래스 대신 배열 사용
            '미디어 파일 목록을 조회했습니다'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/media/{id}",
     *     summary="미디어 파일 삭제",
     *     description="업로드된 미디어 파일을 삭제합니다",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="미디어 ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="파일 삭제 성공"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="파일을 찾을 수 없음"
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            // 실제 구현에서는 데이터베이스에서 미디어 정보 조회
            // $media = Media::findOrFail($id);
            
            // 현재는 ID 기반으로 파일 찾기 시뮬레이션
            $directories = ['uploads/post', 'uploads/page', 'uploads/avatar', 'uploads/general'];
            $found = false;
            
            foreach ($directories as $directory) {
                $files = Storage::disk('public')->files($directory);
                foreach ($files as $file) {
                    if (Str::contains($file, $id)) {
                        Storage::disk('public')->delete($file);
                        $found = true;
                        break 2;
                    }
                }
            }

            if (!$found) {
                return $this->notFoundResponse('파일을 찾을 수 없습니다');
            }

            return $this->deletedResponse('파일이 삭제되었습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'DELETE_FAILED',
                '파일 삭제 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/media/content-image",
     *     summary="에디터용 이미지 업로드",
     *     description="포스트/페이지 콘텐츠 에디터에서 사용할 이미지를 업로드합니다",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image"},
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary",
     *                     description="업로드할 이미지 파일"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="이미지 업로드 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="url", type="string", example="/storage/uploads/content/image.jpg")
     *         )
     *     )
     * )
     */
    public function uploadContentImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|image|max:5120', // 최대 5MB
        ], [
            'image.required' => '이미지를 선택해주세요',
            'image.image' => '이미지 파일만 업로드 가능합니다',
            'image.max' => '이미지 크기는 5MB를 초과할 수 없습니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $image = $request->file('image');
            
            // ImageService를 사용하여 콘텐츠 이미지 업로드
            $result = $this->imageService->uploadContentImage($image, 'posts/content');

            // 에디터에서 사용할 수 있는 형태로 응답
            return response()->json([
                'success' => true,
                'url' => asset($result['url']),
                'filename' => basename($result['path']),
                'original_name' => $result['original_name'],
                'width' => $result['width'],
                'height' => $result['height'],
                'size' => $result['size'],
                'path' => $result['path']
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => '이미지 업로드 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/media/og-image",
     *     summary="OG 이미지 업로드",
     *     description="포스트용 OG(Open Graph) 이미지를 업로드합니다 (최소 1200x630)",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image"},
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary",
     *                     description="업로드할 OG 이미지 파일 (최소 1200x630)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="OG 이미지 업로드 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="url", type="string", example="/storage/uploads/og/image.jpg"),
     *             @OA\Property(property="width", type="integer", example=1200),
     *             @OA\Property(property="height", type="integer", example=630)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="이미지 크기 부족 (최소 1200x630 필요)"
     *     )
     * )
     */
    public function uploadOgImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|image|max:10240', // 최대 10MB
        ], [
            'image.required' => 'OG 이미지를 선택해주세요',
            'image.image' => '이미지 파일만 업로드 가능합니다',
            'image.max' => '이미지 크기는 10MB를 초과할 수 없습니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $image = $request->file('image');
            
            // ImageService를 사용하여 OG 이미지 업로드
            $result = $this->imageService->uploadOgImage($image, 'posts/og');

            return $this->createdResponse([
                'url' => asset(Storage::url($result['path'])),
                'filename' => basename($result['path']),
                'original_name' => $result['original_name'],
                'width' => $result['width'],
                'height' => $result['height'],
                'size' => $result['size'],
                'path' => $result['path']
            ], 'OG 이미지가 업로드되었습니다');

        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse(['image' => [$e->getMessage()]]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'OG_UPLOAD_FAILED',
                'OG 이미지 업로드 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/media/resize",
     *     summary="이미지 리사이즈",
     *     description="업로드된 이미지를 특정 크기로 리사이즈합니다",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"path", "width"},
     *             @OA\Property(property="path", type="string", example="posts/main/image.webp"),
     *             @OA\Property(property="width", type="integer", example=800),
     *             @OA\Property(property="height", type="integer", example=600, description="선택사항 (비율 유지하려면 생략)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="이미지 리사이즈 성공"
     *     )
     * )
     */
    public function resizeImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'width' => 'required|integer|min:10|max:2000',
            'height' => 'nullable|integer|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $path = $request->path;
            $width = $request->width;
            $height = $request->height;

            $resizedPath = $this->imageService->resizeImage($path, $width, $height);

            return $this->successResponse([
                'original_path' => $path,
                'resized_path' => $resizedPath,
                'resized_url' => asset(Storage::url($resizedPath)),
                'width' => $width,
                'height' => $height
            ], '이미지가 리사이즈되었습니다');

        } catch (\InvalidArgumentException $e) {
            return $this->notFoundResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'RESIZE_FAILED',
                '이미지 리사이즈 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/media/stats",
     *     summary="미디어 통계",
     *     description="미디어 파일 통계 정보를 조회합니다 (관리자만 가능)",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="미디어 통계 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="미디어 통계를 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_files", type="integer", example=150),
     *                 @OA\Property(property="total_size", type="integer", example=52428800),
     *                 @OA\Property(property="total_size_mb", type="number", example=50.0),
     *                 @OA\Property(
     *                     property="by_type",
     *                     type="object",
     *                     @OA\Property(property="post", type="integer", example=80),
     *                     @OA\Property(property="page", type="integer", example=20),
     *                     @OA\Property(property="avatar", type="integer", example=10),
     *                     @OA\Property(property="general", type="integer", example=40)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        try {
            // 실제 구현에서는 데이터베이스에서 통계 조회
            // 현재는 임시 데이터
            $stats = [
                'total_files' => 0,
                'total_size' => 0,
                'total_size_mb' => 0.0,
                'by_type' => [
                    'post' => 0,
                    'page' => 0,
                    'avatar' => 0,
                    'general' => 0,
                ],
                'by_month' => []
            ];

            // Storage 디렉토리에서 실제 파일 통계 계산
            $directories = [
                'uploads/post' => 'post',
                'uploads/page' => 'page', 
                'uploads/avatar' => 'avatar',
                'uploads/general' => 'general',
                'uploads/content' => 'general'
            ];

            foreach ($directories as $directory => $type) {
                if (Storage::disk('public')->exists($directory)) {
                    $files = Storage::disk('public')->allFiles($directory);
                    $stats['by_type'][$type] += count($files);
                    $stats['total_files'] += count($files);
                    
                    foreach ($files as $file) {
                        $size = Storage::disk('public')->size($file);
                        $stats['total_size'] += $size;
                    }
                }
            }

            $stats['total_size_mb'] = round($stats['total_size'] / (1024 * 1024), 2);

            return $this->successResponse($stats, '미디어 통계를 조회했습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'STATS_ERROR',
                '통계 조회 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }
}