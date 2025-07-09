<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SlugService;
use App\Traits\ApiResponse;
use App\Models\Post;
use App\Models\Page;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 슬러그 생성 및 관리 컨트롤러
 * 
 * @OA\Tag(
 *     name="Slug",
 *     description="슬러그 생성 및 관리 API"
 * )
 */
class SlugController extends Controller
{
    use ApiResponse;
    
    /**
     * 제목에서 슬러그 생성 미리보기
     * 
     * @OA\Post(
     *     path="/api/v1/slugs/generate",
     *     summary="제목에서 슬러그 생성",
     *     description="한글 또는 영문 제목을 URL 친화적인 슬러그로 변환합니다",
     *     tags={"Slug"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title", type="string", description="변환할 제목", example="안녕하세요 첫 번째 포스트입니다"),
     *             @OA\Property(property="method", type="string", enum={"auto", "korean", "english"}, description="생성 방법", example="auto"),
     *             @OA\Property(property="separator", type="string", description="구분자", example="-"),
     *             @OA\Property(property="type", type="string", enum={"post", "page", "category"}, description="콘텐츠 타입", example="post")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="슬러그 생성 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="슬러그가 생성되었습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="original_title", type="string", example="안녕하세요 첫 번째 포스트입니다"),
     *                 @OA\Property(property="generated_slug", type="string", example="안녕하세요-첫-번째-포스트입니다"),
     *                 @OA\Property(property="unique_slug", type="string", example="안녕하세요-첫-번째-포스트입니다"),
     *                 @OA\Property(property="method_used", type="string", example="auto"),
     *                 @OA\Property(property="separator", type="string", example="-"),
     *                 @OA\Property(property="is_unique", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="validation",
     *                     type="object",
     *                     @OA\Property(property="is_valid", type="boolean", example=true),
     *                     @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *                 ),
     *                 @OA\Property(property="url_preview", type="string", example="http://localhost/posts/안녕하세요-첫-번째-포스트입니다"),
     *                 @OA\Property(property="character_count", type="integer", example=25),
     *                 @OA\Property(property="contains_korean", type="boolean", example=true)
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
     *                 @OA\Property(property="details", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|min:1|max:255',
            'method' => 'sometimes|string|in:auto,korean,english',
            'separator' => 'sometimes|string|in:-,_',
            'type' => 'sometimes|string|in:post,page,category'
        ]);
        
        $title = $validated['title'];
        $method = $validated['method'] ?? 'auto';
        $separator = $validated['separator'] ?? '-';
        $type = $validated['type'] ?? 'post';
        
        // 기본 슬러그 생성
        $preview = SlugService::previewSlug($title, [
            'method' => $method,
            'separator' => $separator
        ]);
        
        // 모델 클래스 결정
        $modelClass = $this->getModelClass($type);
        
        // 고유 슬러그 생성
        $uniqueSlug = SlugService::makeUniqueSlug(
            $preview['generated_slug'], 
            $modelClass
        );
        
        $data = array_merge($preview, [
            'unique_slug' => $uniqueSlug,
            'is_unique' => $uniqueSlug === $preview['generated_slug'],
            'content_type' => $type,
            'suggestions' => $this->generateSuggestions($title, $method, $separator)
        ]);
        
        return $this->successResponse($data, '슬러그가 생성되었습니다');
    }
    
    /**
     * 슬러그 유효성 검사
     * 
     * @OA\Post(
     *     path="/api/v1/slugs/validate",
     *     summary="슬러그 유효성 검사",
     *     description="입력된 슬러그의 유효성과 중복 여부를 검사합니다",
     *     tags={"Slug"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"slug"},
     *             @OA\Property(property="slug", type="string", description="검사할 슬러그", example="my-awesome-post"),
     *             @OA\Property(property="type", type="string", enum={"post", "page", "category"}, description="콘텐츠 타입", example="post"),
     *             @OA\Property(property="exclude_id", type="integer", description="제외할 ID (수정 시)", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="유효성 검사 완료",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="슬러그 유효성 검사가 완료되었습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="slug", type="string", example="my-awesome-post"),
     *                 @OA\Property(property="is_valid", type="boolean", example=true),
     *                 @OA\Property(property="is_unique", type="boolean", example=true),
     *                 @OA\Property(property="validation_errors", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="suggested_slug", type="string", example="my-awesome-post-2")
     *             )
     *         )
     *     )
     * )
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:100',
            'type' => 'sometimes|string|in:post,page,category',
            'exclude_id' => 'sometimes|integer|min:1'
        ]);
        
        $slug = $validated['slug'];
        $type = $validated['type'] ?? 'post';
        $excludeId = $validated['exclude_id'] ?? null;
        
        // 슬러그 형식 유효성 검사
        $validation = SlugService::validateSlug($slug);
        
        // 중복 확인
        $modelClass = $this->getModelClass($type);
        $isUnique = !$this->slugExists($slug, $modelClass, $excludeId);
        
        $data = [
            'slug' => $slug,
            'is_valid' => $validation['is_valid'],
            'is_unique' => $isUnique,
            'validation_errors' => $validation['errors'],
            'content_type' => $type
        ];
        
        // 중복이면 대안 제안
        if (!$isUnique) {
            $data['suggested_slug'] = SlugService::makeUniqueSlug($slug, $modelClass, $excludeId);
        }
        
        return $this->successResponse($data, '슬러그 유효성 검사가 완료되었습니다');
    }
    
    /**
     * 슬러그 일괄 생성 (관리자용)
     * 
     * @OA\Post(
     *     path="/api/v1/slugs/batch-generate",
     *     summary="슬러그 일괄 생성",
     *     description="기존 콘텐츠들의 슬러그를 일괄 재생성합니다 (관리자 전용)",
     *     tags={"Slug"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"post", "page", "category"}, description="콘텐츠 타입", example="post"),
     *             @OA\Property(property="method", type="string", enum={"auto", "korean", "english"}, description="생성 방법", example="auto"),
     *             @OA\Property(property="force_update", type="boolean", description="기존 슬러그 강제 업데이트", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="일괄 생성 완료",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="슬러그 일괄 생성이 완료되었습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_processed", type="integer", example=25),
     *                 @OA\Property(property="updated_count", type="integer", example=15),
     *                 @OA\Property(property="skipped_count", type="integer", example=10),
     *                 @OA\Property(property="failed_count", type="integer", example=0),
     *                 @OA\Property(property="processing_time", type="number", example=1.24)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="권한 부족"
     *     )
     * )
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:post,page,category',
            'method' => 'sometimes|string|in:auto,korean,english',
            'force_update' => 'sometimes|boolean'
        ]);
        
        $type = $validated['type'];
        $method = $validated['method'] ?? 'auto';
        $forceUpdate = $validated['force_update'] ?? false;
        
        $startTime = microtime(true);
        $modelClass = $this->getModelClass($type);
        
        // 처리할 레코드 조회
        $query = $modelClass::query();
        if (!$forceUpdate) {
            $query->where(function($q) {
                $q->whereNull('slug')->orWhere('slug', '');
            });
        }
        
        $records = $query->get();
        $totalProcessed = $records->count();
        $updatedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        
        foreach ($records as $record) {
            try {
                // 기존 슬러그가 있고 강제 업데이트가 아니면 스킵
                if (!$forceUpdate && !empty($record->slug)) {
                    $skippedCount++;
                    continue;
                }
                
                // 제목에서 슬러그 생성
                $title = $record->title ?? $record->name ?? 'untitled';
                
                switch ($method) {
                    case 'korean':
                        $slug = SlugService::generateKoreanSlug($title);
                        break;
                    case 'english':
                        $slug = SlugService::generateEnglishSlug($title);
                        break;
                    default:
                        $slug = SlugService::generateAutoSlug($title);
                }
                
                // 고유 슬러그 생성
                $uniqueSlug = SlugService::makeUniqueSlug($slug, $modelClass, $record->id);
                
                // 업데이트
                $record->update(['slug' => $uniqueSlug]);
                $updatedCount++;
                
            } catch (\Exception $e) {
                $failedCount++;
                \Log::error("슬러그 생성 실패: {$record->id} - " . $e->getMessage());
            }
        }
        
        $processingTime = round(microtime(true) - $startTime, 2);
        
        $data = [
            'total_processed' => $totalProcessed,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'processing_time' => $processingTime,
            'content_type' => $type,
            'method_used' => $method,
            'force_update' => $forceUpdate
        ];
        
        return $this->successResponse($data, '슬러그 일괄 생성이 완료되었습니다');
    }
    
    /**
     * 모델 클래스 반환
     */
    private function getModelClass(string $type): string
    {
        return match($type) {
            'page' => Page::class,
            'category' => Category::class,
            default => Post::class,
        };
    }
    
    /**
     * 슬러그 존재 여부 확인
     */
    private function slugExists(string $slug, string $modelClass, ?int $excludeId = null): bool
    {
        return SlugService::slugExists($slug, $modelClass, $excludeId);
    }
    
    /**
     * 슬러그 제안 생성
     */
    private function generateSuggestions(string $title, string $method, string $separator): array
    {
        $suggestions = [];
        
        // 자동 생성 (기본)
        if ($method !== 'auto') {
            $suggestions['auto'] = SlugService::generateAutoSlug($title, $separator);
        }
        
        // 한글 방식
        if ($method !== 'korean') {
            $suggestions['korean'] = SlugService::generateKoreanSlug($title, $separator);
        }
        
        // 영문 방식
        if ($method !== 'english') {
            $suggestions['english'] = SlugService::generateEnglishSlug($title, $separator);
        }
        
        // 다른 구분자
        if ($separator === '-') {
            $suggestions['underscore'] = SlugService::generateAutoSlug($title, '_');
        } else {
            $suggestions['hyphen'] = SlugService::generateAutoSlug($title, '-');
        }
        
        return array_filter($suggestions);
    }
}