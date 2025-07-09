<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Services\ImageService;
use App\Services\CacheService;
use App\Services\SlugService;
use App\Http\Resources\PostResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Posts",
 *     description="포스트 관리 API"
 * )
 */
class PostController extends Controller
{
    use ApiResponse;

    private ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/posts",
     *     summary="포스트 목록 조회",
     *     description="페이지네이션된 포스트 목록을 조회합니다",
     *     tags={"Posts"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="페이지 번호",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="페이지당 아이템 수",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="카테고리 슬러그",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="검색어",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="포스트 목록 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="포스트 목록을 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Post")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="has_more", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'category' => 'string|exists:categories,slug',
            'search' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $perPage = $request->get('per_page', 15);

        // 캐시 키 생성
        $cacheParams = [
            'category' => $request->get('category'),
            'search' => $request->get('search'),
            'page' => $request->get('page', 1),
            'per_page' => $perPage
        ];
        $cacheKey = CacheService::getPostListCacheKey($cacheParams);

        // 캐시된 데이터 확인
        $posts = cache()->remember($cacheKey, config('optimize.post_list_cache_ttl', 1800), function () use ($request, $perPage) {
            $query = Post::published()
                ->with(['category', 'tags', 'user', 'seoMeta'])
                ->latest('published_at');

            // 카테고리 필터링
            if ($request->filled('category')) {
                $category = Category::where('slug', $request->category)->first();
                if ($category) {
                    $query->where('category_id', $category->id);
                }
            }

            // 검색어 필터링
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%")
                      ->orWhere('summary', 'like', "%{$search}%");
                });
            }

            return $query->paginate($perPage);
        });

        return $this->paginatedResponse($posts, PostResource::class, '포스트 목록을 조회했습니다');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/posts/{slug}",
     *     summary="포스트 상세 조회",
     *     description="슬러그로 포스트 상세 정보를 조회합니다",
     *     tags={"Posts"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="포스트 슬러그",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="포스트 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="포스트를 조회했습니다"),
     *             @OA\Property(property="data", ref="#/components/schemas/Post")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="포스트를 찾을 수 없음",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="포스트를 찾을 수 없습니다")
     *             )
     *         )
     *     )
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $cacheKey = CacheService::getPostCacheKey($slug);
        
        $post = cache()->remember($cacheKey, config('optimize.post_cache_ttl', 3600), function () use ($slug) {
            return Post::published()
                ->with(['category', 'tags', 'user', 'seoMeta', 'comments' => function ($query) {
                    $query->approved()->whereNull('parent_id')->with('user')->latest();
                }])
                ->where('slug', $slug)
                ->first();
        });

        if (!$post) {
            return $this->notFoundResponse('포스트를 찾을 수 없습니다');
        }

        // 조회수 증가 (Analytics 기록)
        $this->recordPostView($post);

        return $this->successResponse(
            new PostResource($post),
            '포스트를 조회했습니다'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/posts",
     *     summary="포스트 생성",
     *     description="새로운 포스트를 생성합니다 (작성자 권한 필요)",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "content"},
     *             @OA\Property(property="title", type="string", example="새로운 포스트 제목"),
     *             @OA\Property(property="content", type="string", example="포스트 내용입니다..."),
     *             @OA\Property(property="summary", type="string", example="포스트 요약"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"PHP", "Laravel"}),
     *             @OA\Property(property="status", type="string", enum={"draft", "published"}, example="draft"),
     *             @OA\Property(property="main_image", type="string", example="path/to/image.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="포스트 생성 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="포스트가 생성되었습니다"),
     *             @OA\Property(property="data", ref="#/components/schemas/Post")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'summary' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'status' => 'nullable|in:draft,published',
            'main_image' => 'nullable|string',
            'og_image' => 'nullable|string',
        ], [
            'title.required' => '제목을 입력해주세요',
            'content.required' => '내용을 입력해주세요',
            'category_id.exists' => '존재하지 않는 카테고리입니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            DB::beginTransaction();

            $post = Post::create([
                'title' => $request->title,
                'content' => $request->content,
                'summary' => $request->summary,
                'category_id' => $request->category_id,
                'user_id' => Auth::id(),
                'status' => $request->get('status', 'draft'),
                'main_image' => $request->main_image,
                'og_image' => $request->og_image,
                'published_at' => $request->get('status') === 'published' ? now() : null,
            ]);

            // 태그 연결
            if ($request->filled('tags')) {
                $tagIds = [];
                foreach ($request->tags as $tagName) {
                    $tag = Tag::firstOrCreate(['name' => $tagName], [
                        'slug' => SlugService::generateAutoSlug($tagName),
                        'color' => sprintf("#%06x", mt_rand(0, 0xFFFFFF))
                    ]);
                    $tagIds[] = $tag->id;
                }
                $post->tags()->sync($tagIds);
            }

            // 캐시 무효화
            CacheService::clearPostListCache();

            DB::commit();

            $post->load(['category', 'tags', 'user']);

            return $this->createdResponse(
                new PostResource($post),
                '포스트가 생성되었습니다'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverErrorResponse('포스트 생성 중 오류가 발생했습니다');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/posts/{id}",
     *     summary="포스트 수정",
     *     description="포스트를 수정합니다 (작성자 또는 관리자 권한 필요)",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/PostRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="포스트 수정 성공"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="권한 없음"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="포스트를 찾을 수 없음"
     *     )
     * )
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        // 권한 확인
        if (!Auth::user()->isAdmin() && $post->user_id !== Auth::id()) {
            return $this->forbiddenResponse('포스트를 수정할 권한이 없습니다');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'summary' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'status' => 'nullable|in:draft,published',
            'main_image' => 'nullable|string',
            'og_image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            DB::beginTransaction();

            $post->update([
                'title' => $request->title,
                'content' => $request->content,
                'summary' => $request->summary,
                'category_id' => $request->category_id,
                'status' => $request->get('status', $post->status),
                'main_image' => $request->main_image,
                'og_image' => $request->og_image,
                'published_at' => $request->get('status') === 'published' && !$post->published_at 
                    ? now() 
                    : $post->published_at,
            ]);

            // 태그 연결
            if ($request->has('tags')) {
                $tagIds = [];
                foreach ($request->tags as $tagName) {
                    $tag = Tag::firstOrCreate(['name' => $tagName], [
                        'slug' => SlugService::generateAutoSlug($tagName),
                        'color' => sprintf("#%06x", mt_rand(0, 0xFFFFFF))
                    ]);
                    $tagIds[] = $tag->id;
                }
                $post->tags()->sync($tagIds);
            }

            // 캐시 무효화
            CacheService::clearPostCache($post->slug);
            CacheService::clearPostListCache();

            DB::commit();

            $post->load(['category', 'tags', 'user']);

            return $this->updatedResponse(
                new PostResource($post),
                '포스트가 수정되었습니다'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverErrorResponse('포스트 수정 중 오류가 발생했습니다');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/posts/{id}",
     *     summary="포스트 삭제",
     *     description="포스트를 삭제합니다 (작성자 또는 관리자 권한 필요)",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="포스트 삭제 성공"
     *     )
     * )
     */
    public function destroy(Post $post): JsonResponse
    {
        // 권한 확인
        if (!Auth::user()->isAdmin() && $post->user_id !== Auth::id()) {
            return $this->forbiddenResponse('포스트를 삭제할 권한이 없습니다');
        }

        try {
            DB::beginTransaction();

            // 연관된 데이터 삭제
            $post->tags()->detach();
            $post->comments()->delete();
            $post->attachments()->delete();
            $post->seoMeta()->delete();

            // 캐시 무효화
            CacheService::clearPostCache($post->slug);
            CacheService::clearPostListCache();

            $post->delete();

            DB::commit();

            return $this->deletedResponse('포스트가 삭제되었습니다');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverErrorResponse('포스트 삭제 중 오류가 발생했습니다');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/posts/{id}/related",
     *     summary="연관 포스트 조회",
     *     description="지정된 포스트와 연관된 포스트들을 조회합니다",
     *     tags={"Posts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="연관 포스트 조회 성공"
     *     )
     * )
     */
    public function related(Post $post): JsonResponse
    {
        $relatedPosts = Post::published()
            ->where('id', '!=', $post->id)
            ->where(function ($query) use ($post) {
                // 같은 카테고리 또는 공통 태그가 있는 포스트
                $query->where('category_id', $post->category_id)
                      ->orWhereHas('tags', function ($q) use ($post) {
                          $q->whereIn('tags.id', $post->tags->pluck('id'));
                      });
            })
            ->with(['category', 'tags', 'user'])
            ->latest('published_at')
            ->limit(5)
            ->get();

        return $this->successResponse(
            PostResource::collection($relatedPosts),
            '연관 포스트를 조회했습니다'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/posts/generate-slug",
     *     summary="포스트 슬러그 생성",
     *     description="제목에서 슬러그를 자동 생성합니다",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title", type="string", example="안녕하세요 새로운 포스트입니다")
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
     *                 @OA\Property(property="slug", type="string", example="안녕하세요-새로운-포스트입니다"),
     *                 @OA\Property(property="is_unique", type="boolean", example=true)
     *             )
     *         )
     *     )
     * )
     */
    public function generateSlug(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $baseSlug = SlugService::generateAutoSlug($request->title);
        $uniqueSlug = SlugService::makeUniqueSlug($baseSlug, Post::class);

        return $this->successResponse([
            'slug' => $uniqueSlug,
            'is_unique' => $baseSlug === $uniqueSlug,
            'suggestions' => [
                'korean' => SlugService::generateKoreanSlug($request->title),
                'english' => SlugService::generateEnglishSlug($request->title)
            ]
        ], '슬러그가 생성되었습니다');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/posts/{id}/publish",
     *     summary="포스트 발행",
     *     description="초안 포스트를 발행상태로 변경합니다",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="포스트 발행 성공"
     *     )
     * )
     */
    public function publish(Post $post): JsonResponse
    {
        // 권한 확인
        if (!Auth::user()->isAdmin() && $post->user_id !== Auth::id()) {
            return $this->forbiddenResponse('포스트를 발행할 권한이 없습니다');
        }

        if ($post->status === 'published') {
            return $this->businessErrorResponse(
                'ALREADY_PUBLISHED', 
                '이미 발행된 포스트입니다'
            );
        }

        $post->publish();

        // 캐시 무효화
        CacheService::clearPostListCache();

        return $this->successResponse(
            new PostResource($post->load(['category', 'tags', 'user'])),
            '포스트가 발행되었습니다'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/posts/{id}/unpublish",
     *     summary="포스트 발행 취소",
     *     description="발행된 포스트를 초안상태로 변경합니다",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="포스트 발행 취소 성공"
     *     )
     * )
     */
    public function unpublish(Post $post): JsonResponse
    {
        // 권한 확인
        if (!Auth::user()->isAdmin() && $post->user_id !== Auth::id()) {
            return $this->forbiddenResponse('포스트를 수정할 권한이 없습니다');
        }

        if ($post->status === 'draft') {
            return $this->businessErrorResponse(
                'ALREADY_DRAFT', 
                '이미 초안상태인 포스트입니다'
            );
        }

        $post->update([
            'status' => 'draft',
            'published_at' => null
        ]);

        // 캐시 무효화
        CacheService::clearPostListCache();
        CacheService::clearPostCache($post->slug);

        return $this->successResponse(
            new PostResource($post->load(['category', 'tags', 'user'])),
            '포스트 발행이 취소되었습니다'
        );
    }

    /**
     * 포스트 조회 기록
     */
    private function recordPostView(Post $post): void
    {
        // Analytics 기록 로직 (비동기 처리 권장)
        try {
            \App\Models\Analytics::create([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'url' => request()->fullUrl(),
                'referer' => request()->header('referer'),
                'post_id' => $post->id,
                'event_type' => 'post_view',
            ]);
            
            // 조회수 증가
            $post->incrementViews();
            
        } catch (\Exception $e) {
            // 로그만 남기고 계속 진행
            \Log::warning('Analytics recording failed: ' . $e->getMessage());
        }
    }
}