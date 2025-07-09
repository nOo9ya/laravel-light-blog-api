<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use App\Http\Resources\PostResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TagResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Search",
 *     description="검색 API"
 * )
 */
class SearchController extends Controller
{
    use ApiResponse;

    private SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search",
     *     summary="통합 검색",
     *     description="포스트, 페이지, 카테고리, 태그를 통합 검색합니다",
     *     tags={"Search"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="검색어",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="검색 타입 (all, posts, pages, categories, tags)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"all", "posts", "pages", "categories", "tags"}, default="all")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="각 타입별 최대 결과 수",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, maximum=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="검색 완료",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="검색이 완료되었습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="posts", type="array", @OA\Items(ref="#/components/schemas/Post")),
     *                 @OA\Property(property="pages", type="array", @OA\Items(ref="#/components/schemas/Page")),
     *                 @OA\Property(property="categories", type="array", @OA\Items(ref="#/components/schemas/Category")),
     *                 @OA\Property(property="tags", type="array", @OA\Items(ref="#/components/schemas/Tag")),
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="query", type="string", example="Laravel")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:100',
            'type' => 'nullable|string|in:all,posts,pages,categories,tags',
            'limit' => 'integer|min:1|max:50',
        ], [
            'q.required' => '검색어를 입력해주세요',
            'q.min' => '검색어는 최소 2글자 이상 입력해주세요',
            'q.max' => '검색어는 100글자를 초과할 수 없습니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = $request->get('q');
        $type = $request->get('type', 'all');
        $limit = $request->get('limit', 10);

        try {
            // 검색 실행
            $results = $this->searchService->search($query, $type, $limit);
            
            // 검색 로그 기록
            $this->searchService->logSearch($query, $type, $results['total'], $request->ip());
            
            return $this->successResponse($results, '검색이 완료되었습니다');
            
        } catch (\Exception $e) {
            return $this->serverErrorResponse('검색 중 오류가 발생했습니다: ' . $e->getMessage());
        }

        // 결과를 리소스로 변환
        $transformedResults = [
            'query' => $results['query'],
            'total' => $results['total'],
        ];

        if (isset($results['posts'])) {
            $transformedResults['posts'] = PostResource::collection($results['posts']);
        }

        if (isset($results['pages'])) {
            $transformedResults['pages'] = PageResource::collection($results['pages']);
        }

        if (isset($results['categories'])) {
            $transformedResults['categories'] = CategoryResource::collection($results['categories']);
        }

        if (isset($results['tags'])) {
            $transformedResults['tags'] = TagResource::collection($results['tags']);
        }

        return $this->successResponse(
            $transformedResults,
            '검색이 완료되었습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/posts",
     *     summary="포스트 검색 (페이지네이션)",
     *     description="포스트만 검색하며 페이지네이션을 지원합니다",
     *     tags={"Search"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="검색어",
     *         required=true,
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
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="포스트 검색 완료"
     *     )
     * )
     */
    public function posts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:100',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = $request->get('q');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);

        $results = $this->searchService->searchWithPagination($query, ['posts'], $perPage, $page);

        return $this->paginatedResponse(
            $results['posts'],
            PostResource::class,
            "'{$query}' 검색 결과입니다"
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/pages",
     *     summary="페이지 검색 (페이지네이션)",
     *     description="페이지만 검색하며 페이지네이션을 지원합니다",
     *     tags={"Search"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="검색어",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="페이지 검색 완료"
     *     )
     * )
     */
    public function pages(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:100',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = $request->get('q');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);

        $results = $this->searchService->searchWithPagination($query, ['pages'], $perPage, $page);

        return $this->paginatedResponse(
            $results['pages'],
            PageResource::class,
            "'{$query}' 페이지 검색 결과입니다"
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/autocomplete",
     *     summary="검색 자동완성",
     *     description="검색어 자동완성 제안을 제공합니다",
     *     tags={"Search"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="검색어",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="제안 수",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, maximum=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="자동완성 제안 완료",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="검색 제안을 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="type", type="string", example="post"),
     *                     @OA\Property(property="title", type="string", example="Laravel 튜토리얼"),
     *                     @OA\Property(property="url", type="string", example="/api/v1/posts/laravel-tutorial")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:100',
            'limit' => 'integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = $request->get('q');
        $limit = $request->get('limit', 10);

        $suggestions = $this->searchService->getAutocompleteSuggestions($query, $limit);

        // API용 URL로 변경
        $suggestions = array_map(function ($suggestion) {
            if ($suggestion['type'] === 'post') {
                $suggestion['url'] = "/api/v1/posts/" . basename($suggestion['url']);
            } elseif ($suggestion['type'] === 'category') {
                $suggestion['url'] = "/api/v1/categories/" . basename($suggestion['url']);
            } elseif ($suggestion['type'] === 'tag') {
                $suggestion['url'] = "/api/v1/tags/" . basename($suggestion['url']);
            }
            return $suggestion;
        }, $suggestions);

        return $this->successResponse(
            $suggestions,
            '검색 제안을 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/popular",
     *     summary="인기 검색어",
     *     description="인기 검색어 목록을 조회합니다",
     *     tags={"Search"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="조회할 검색어 수",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, maximum=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="인기 검색어 조회 완료",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="인기 검색어를 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="query", type="string", example="Laravel"),
     *                     @OA\Property(property="count", type="integer", example=45)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function popular(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $limit = $request->get('limit', 10);
        $popularSearches = $this->searchService->getPopularSearches($limit);

        return $this->successResponse(
            $popularSearches,
            '인기 검색어를 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/related",
     *     summary="관련 검색어",
     *     description="특정 검색어와 관련된 검색어 목록을 조회합니다",
     *     tags={"Search"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="기준 검색어",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="조회할 검색어 수",
     *         required=false,
     *         @OA\Schema(type="integer", default=5, maximum=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="관련 검색어 조회 완료"
     *     )
     * )
     */
    public function related(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:100',
            'limit' => 'integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = $request->get('q');
        $limit = $request->get('limit', 5);

        $relatedSearches = $this->searchService->getRelatedSearches($query, $limit);

        return $this->successResponse(
            $relatedSearches,
            '관련 검색어를 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/suggestions",
     *     summary="검색 제안 모음",
     *     description="자동완성, 인기검색어, 관련검색어를 모두 포함한 검색 제안을 제공합니다",
     *     tags={"Search"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="검색어 (선택사항)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="검색 제안 조회 완료"
     *     )
     * )
     */
    public function suggestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'nullable|string|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = $request->get('q');
        $suggestions = [
            'popular' => $this->searchService->getPopularSearches(10),
        ];

        if ($query) {
            $suggestions['autocomplete'] = $this->searchService->getAutocompleteSuggestions($query, 5);
            $suggestions['related'] = $this->searchService->getRelatedSearches($query, 5);
        }

        return $this->successResponse(
            $suggestions,
            '검색 제안을 조회했습니다'
        );
    }
}