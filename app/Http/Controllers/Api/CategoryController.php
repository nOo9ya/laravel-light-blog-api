<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PostResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Categories",
 *     description="카테고리 관리 API"
 * )
 */
class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/categories",
     *     summary="카테고리 목록 조회",
     *     description="활성화된 카테고리 목록을 계층구조로 조회합니다",
     *     tags={"Categories"},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="카테고리 타입 (post, page, both)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"post", "page", "both"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="카테고리 목록 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="카테고리 목록을 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Category")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:post,page,both',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = Category::active()
            ->with(['children' => function ($query) {
                $query->active()->orderBy('order');
            }])
            ->whereNull('parent_id')
            ->orderBy('order');

        // 타입 필터링
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $categories = $query->get();

        return $this->successResponse(
            CategoryResource::collection($categories),
            '카테고리 목록을 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/{slug}",
     *     summary="카테고리 상세 조회",
     *     description="슬러그로 카테고리 상세 정보를 조회합니다",
     *     tags={"Categories"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="카테고리 슬러그",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="카테고리 조회 성공"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="카테고리를 찾을 수 없음"
     *     )
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::active()
            ->with(['children' => function ($query) {
                $query->active()->orderBy('order');
            }, 'parent'])
            ->where('slug', $slug)
            ->first();

        if (!$category) {
            return $this->notFoundResponse('카테고리를 찾을 수 없습니다');
        }

        return $this->successResponse(
            new CategoryResource($category),
            '카테고리를 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/{slug}/posts",
     *     summary="카테고리별 포스트 목록 조회",
     *     description="특정 카테고리에 속한 포스트 목록을 조회합니다",
     *     tags={"Categories"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="카테고리 슬러그",
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
     *         description="카테고리별 포스트 목록 조회 성공"
     *     )
     * )
     */
    public function posts(Request $request, string $slug): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $category = Category::active()->where('slug', $slug)->first();

        if (!$category) {
            return $this->notFoundResponse('카테고리를 찾을 수 없습니다');
        }

        $perPage = $request->get('per_page', 15);

        // 하위 카테고리 ID들도 포함
        $categoryIds = [$category->id];
        if ($category->children->count() > 0) {
            $categoryIds = array_merge($categoryIds, $category->children->pluck('id')->toArray());
        }

        $posts = Post::published()
            ->whereIn('category_id', $categoryIds)
            ->with(['category', 'tags', 'user'])
            ->latest('published_at')
            ->paginate($perPage);

        return $this->paginatedResponse(
            $posts, 
            PostResource::class, 
            "'{$category->name}' 카테고리의 포스트 목록을 조회했습니다"
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/{slug}/children",
     *     summary="하위 카테고리 목록 조회",
     *     description="특정 카테고리의 하위 카테고리 목록을 조회합니다",
     *     tags={"Categories"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="부모 카테고리 슬러그",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="하위 카테고리 목록 조회 성공"
     *     )
     * )
     */
    public function children(string $slug): JsonResponse
    {
        $category = Category::active()->where('slug', $slug)->first();

        if (!$category) {
            return $this->notFoundResponse('카테고리를 찾을 수 없습니다');
        }

        $children = Category::active()
            ->where('parent_id', $category->id)
            ->orderBy('order')
            ->get();

        return $this->successResponse(
            CategoryResource::collection($children),
            '하위 카테고리 목록을 조회했습니다'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/categories",
     *     summary="카테고리 생성",
     *     description="새로운 카테고리를 생성합니다 (관리자 권한 필요)",
     *     tags={"Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="새 카테고리"),
     *             @OA\Property(property="description", type="string", example="카테고리 설명"),
     *             @OA\Property(property="parent_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", enum={"post", "page", "both"}, example="post"),
     *             @OA\Property(property="order", type="integer", example=0),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="카테고리 생성 성공"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:post,page,both',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ], [
            'name.required' => '카테고리 이름을 입력해주세요',
            'type.required' => '카테고리 타입을 선택해주세요',
            'parent_id.exists' => '존재하지 않는 부모 카테고리입니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // 부모 카테고리가 있는 경우 순환 참조 체크
        if ($request->filled('parent_id')) {
            $parentCategory = Category::find($request->parent_id);
            if ($parentCategory && $parentCategory->parent_id) {
                return $this->businessErrorResponse(
                    'INVALID_HIERARCHY',
                    '3단계 이상의 계층 구조는 지원하지 않습니다'
                );
            }
        }

        $category = Category::create([
            'name' => $request->name,
            'slug' => \Str::slug($request->name),
            'description' => $request->description,
            'parent_id' => $request->parent_id,
            'type' => $request->type,
            'order' => $request->get('order', 0),
            'is_active' => $request->get('is_active', true),
        ]);

        return $this->createdResponse(
            new CategoryResource($category),
            '카테고리가 생성되었습니다'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/categories/{id}",
     *     summary="카테고리 수정",
     *     description="카테고리를 수정합니다 (관리자 권한 필요)",
     *     tags={"Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="카테고리 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CategoryRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="카테고리 수정 성공"
     *     )
     * )
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:post,page,both',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // 자기 자신을 부모로 설정하는 것 방지
        if ($request->filled('parent_id') && $request->parent_id == $category->id) {
            return $this->businessErrorResponse(
                'INVALID_PARENT',
                '자기 자신을 부모 카테고리로 설정할 수 없습니다'
            );
        }

        // 순환 참조 체크
        if ($request->filled('parent_id')) {
            $parentCategory = Category::find($request->parent_id);
            if ($parentCategory && $parentCategory->parent_id == $category->id) {
                return $this->businessErrorResponse(
                    'CIRCULAR_REFERENCE',
                    '순환 참조가 발생할 수 있는 구조입니다'
                );
            }
        }

        $category->update([
            'name' => $request->name,
            'slug' => \Str::slug($request->name),
            'description' => $request->description,
            'parent_id' => $request->parent_id,
            'type' => $request->type,
            'order' => $request->get('order', $category->order),
            'is_active' => $request->get('is_active', $category->is_active),
        ]);

        return $this->updatedResponse(
            new CategoryResource($category),
            '카테고리가 수정되었습니다'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/categories/{id}",
     *     summary="카테고리 삭제",
     *     description="카테고리를 삭제합니다 (관리자 권한 필요)",
     *     tags={"Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="카테고리 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="카테고리 삭제 성공"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="삭제할 수 없음 (하위 카테고리 또는 연관된 포스트 존재)"
     *     )
     * )
     */
    public function destroy(Category $category): JsonResponse
    {
        // 하위 카테고리가 있는지 확인
        if ($category->children()->count() > 0) {
            return $this->businessErrorResponse(
                'HAS_CHILDREN',
                '하위 카테고리가 있는 카테고리는 삭제할 수 없습니다'
            );
        }

        // 연관된 포스트가 있는지 확인
        if ($category->posts()->count() > 0) {
            return $this->businessErrorResponse(
                'HAS_POSTS',
                '포스트가 연결된 카테고리는 삭제할 수 없습니다'
            );
        }

        $category->delete();

        return $this->deletedResponse('카테고리가 삭제되었습니다');
    }
}