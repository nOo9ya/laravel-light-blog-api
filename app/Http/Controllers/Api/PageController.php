<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Category;
use App\Http\Resources\PageResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Pages",
 *     description="페이지 관리 API"
 * )
 */
class PageController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/pages",
     *     summary="페이지 목록 조회",
     *     description="발행된 페이지 목록을 조회합니다",
     *     tags={"Pages"},
     *     @OA\Parameter(
     *         name="menu_only",
     *         in="query",
     *         description="메뉴에 표시되는 페이지만 조회",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="카테고리 슬러그",
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
     *         description="페이지 목록 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="페이지 목록을 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Page")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'menu_only' => 'boolean',
            'category' => 'string|exists:categories,slug',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = Page::published()->with(['user', 'category']);

        // 메뉴에 표시되는 페이지만 조회
        if ($request->boolean('menu_only')) {
            $query->inMenu();
        } else {
            $query->ordered();
        }

        // 카테고리 필터링
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        $perPage = $request->get('per_page', 20);
        $pages = $query->paginate($perPage);

        return $this->paginatedResponse(
            $pages,
            PageResource::class,
            '페이지 목록을 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/menu",
     *     summary="메뉴용 페이지 목록 조회",
     *     description="메뉴에 표시되는 페이지 목록을 조회합니다",
     *     tags={"Pages"},
     *     @OA\Response(
     *         response=200,
     *         description="메뉴 페이지 목록 조회 성공"
     *     )
     * )
     */
    public function menu(): JsonResponse
    {
        $pages = Page::published()
            ->inMenu()
            ->with(['category'])
            ->get();

        return $this->successResponse(
            PageResource::collection($pages),
            '메뉴 페이지 목록을 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pages/{slug}",
     *     summary="페이지 상세 조회",
     *     description="슬러그로 페이지 상세 정보를 조회합니다",
     *     tags={"Pages"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="페이지 슬러그",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="페이지 조회 성공"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="페이지를 찾을 수 없음"
     *     )
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $page = Page::published()
            ->with(['user', 'category'])
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return $this->notFoundResponse('페이지를 찾을 수 없습니다');
        }

        return $this->successResponse(
            new PageResource($page),
            '페이지를 조회했습니다'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/pages",
     *     summary="페이지 생성",
     *     description="새로운 페이지를 생성합니다 (관리자 권한 필요)",
     *     tags={"Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "content"},
     *             @OA\Property(property="title", type="string", example="About Us"),
     *             @OA\Property(property="content", type="string", example="페이지 내용입니다..."),
     *             @OA\Property(property="excerpt", type="string", example="페이지 요약"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="meta_title", type="string", example="About Us - 우리 회사"),
     *             @OA\Property(property="meta_description", type="string", example="우리 회사에 대한 소개"),
     *             @OA\Property(property="is_published", type="boolean", example=true),
     *             @OA\Property(property="show_in_menu", type="boolean", example=true),
     *             @OA\Property(property="order", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="페이지 생성 성공"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'show_in_menu' => 'boolean',
            'order' => 'integer|min:0',
        ], [
            'title.required' => '페이지 제목을 입력해주세요',
            'content.required' => '페이지 내용을 입력해주세요',
            'category_id.exists' => '존재하지 않는 카테고리입니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $page = Page::create([
            'title' => $request->title,
            'content' => $request->content,
            'excerpt' => $request->excerpt,
            'category_id' => $request->category_id,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'is_published' => $request->get('is_published', true),
            'show_in_menu' => $request->get('show_in_menu', false),
            'order' => $request->get('order', 0),
            'user_id' => Auth::id(),
        ]);

        $page->load(['user', 'category']);

        return $this->createdResponse(
            new PageResource($page),
            '페이지가 생성되었습니다'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/pages/{id}",
     *     summary="페이지 수정",
     *     description="페이지를 수정합니다 (관리자 권한 필요)",
     *     tags={"Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="페이지 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/PageRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="페이지 수정 성공"
     *     )
     * )
     */
    public function update(Request $request, Page $page): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'show_in_menu' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $page->update([
            'title' => $request->title,
            'content' => $request->content,
            'excerpt' => $request->excerpt,
            'category_id' => $request->category_id,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'is_published' => $request->get('is_published', $page->is_published),
            'show_in_menu' => $request->get('show_in_menu', $page->show_in_menu),
            'order' => $request->get('order', $page->order),
        ]);

        $page->load(['user', 'category']);

        return $this->updatedResponse(
            new PageResource($page),
            '페이지가 수정되었습니다'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/pages/{id}",
     *     summary="페이지 삭제",
     *     description="페이지를 삭제합니다 (관리자 권한 필요)",
     *     tags={"Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="페이지 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="페이지 삭제 성공"
     *     )
     * )
     */
    public function destroy(Page $page): JsonResponse
    {
        $page->delete();

        return $this->deletedResponse('페이지가 삭제되었습니다');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/pages",
     *     summary="관리자용 페이지 목록 조회",
     *     description="모든 페이지를 상태별로 조회합니다 (관리자만 가능)",
     *     tags={"Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="published",
     *         in="query",
     *         description="발행 상태",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="페이지 목록 조회 성공"
     *     )
     * )
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'published' => 'boolean',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = Page::with(['user', 'category'])->latest();

        if ($request->has('published')) {
            if ($request->boolean('published')) {
                $query->published();
            } else {
                $query->where('is_published', false);
            }
        }

        $perPage = $request->get('per_page', 20);
        $pages = $query->paginate($perPage);

        return $this->paginatedResponse(
            $pages,
            PageResource::class,
            '페이지 목록을 조회했습니다'
        );
    }
}