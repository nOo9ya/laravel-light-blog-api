<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\Post;
use App\Http\Resources\TagResource;
use App\Http\Resources\PostResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Tags",
 *     description="태그 관리 API"
 * )
 */
class TagController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/tags",
     *     summary="태그 목록 조회",
     *     description="활성화된 태그 목록을 조회합니다",
     *     tags={"Tags"},
     *     @OA\Parameter(
     *         name="popular",
     *         in="query",
     *         description="인기 태그만 조회 (post_count > 0)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="조회할 태그 수 제한",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="태그 목록 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="태그 목록을 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Tag")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'popular' => 'boolean',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $query = Tag::query();

        // 인기 태그만 조회
        if ($request->boolean('popular')) {
            $query->withPosts();
        }

        $limit = $request->get('limit', 50);
        $tags = $query->orderBy('post_count', 'desc')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $this->successResponse(
            TagResource::collection($tags),
            '태그 목록을 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tags/cloud",
     *     summary="태그 클라우드 조회",
     *     description="태그 클라우드용 인기 태그 목록을 조회합니다",
     *     tags={"Tags"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="조회할 태그 수",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="태그 클라우드 조회 성공"
     *     )
     * )
     */
    public function cloud(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $limit = $request->get('limit', 50);
        $tags = Tag::getTagCloud($limit);

        return $this->successResponse(
            TagResource::collection($tags),
            '태그 클라우드를 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tags/{slug}",
     *     summary="태그 상세 조회",
     *     description="슬러그로 태그 상세 정보를 조회합니다",
     *     tags={"Tags"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="태그 슬러그",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="태그 조회 성공"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="태그를 찾을 수 없음"
     *     )
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $tag = Tag::where('slug', $slug)->first();

        if (!$tag) {
            return $this->notFoundResponse('태그를 찾을 수 없습니다');
        }

        return $this->successResponse(
            new TagResource($tag),
            '태그를 조회했습니다'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tags/{slug}/posts",
     *     summary="태그별 포스트 목록 조회",
     *     description="특정 태그에 속한 포스트 목록을 조회합니다",
     *     tags={"Tags"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="태그 슬러그",
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
     *         description="태그별 포스트 목록 조회 성공"
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

        $tag = Tag::where('slug', $slug)->first();

        if (!$tag) {
            return $this->notFoundResponse('태그를 찾을 수 없습니다');
        }

        $perPage = $request->get('per_page', 15);

        $posts = Post::published()
            ->whereHas('tags', function ($query) use ($tag) {
                $query->where('tags.id', $tag->id);
            })
            ->with(['category', 'tags', 'user'])
            ->latest('published_at')
            ->paginate($perPage);

        return $this->paginatedResponse(
            $posts, 
            PostResource::class, 
            "'{$tag->name}' 태그의 포스트 목록을 조회했습니다"
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tags",
     *     summary="태그 생성",
     *     description="새로운 태그를 생성합니다 (관리자 권한 필요)",
     *     tags={"Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Laravel"),
     *             @OA\Property(property="description", type="string", example="Laravel 프레임워크 관련 태그"),
     *             @OA\Property(property="color", type="string", example="#3b82f6")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="태그 생성 성공"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:tags,name',
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ], [
            'name.required' => '태그 이름을 입력해주세요',
            'name.unique' => '이미 존재하는 태그 이름입니다',
            'color.regex' => '색상은 #FFFFFF 형식으로 입력해주세요',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $tag = Tag::create([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->get('color', sprintf("#%06x", mt_rand(0, 0xFFFFFF))),
        ]);

        return $this->createdResponse(
            new TagResource($tag),
            '태그가 생성되었습니다'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/tags/{id}",
     *     summary="태그 수정",
     *     description="태그를 수정합니다 (관리자 권한 필요)",
     *     tags={"Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="태그 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/TagRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="태그 수정 성공"
     *     )
     * )
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:tags,name,' . $tag->id,
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $tag->update([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->get('color', $tag->color),
        ]);

        return $this->updatedResponse(
            new TagResource($tag),
            '태그가 수정되었습니다'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tags/{id}",
     *     summary="태그 삭제",
     *     description="태그를 삭제합니다 (관리자 권한 필요)",
     *     tags={"Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="태그 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="태그 삭제 성공"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="삭제할 수 없음 (연관된 포스트 존재)"
     *     )
     * )
     */
    public function destroy(Tag $tag): JsonResponse
    {
        // 연관된 포스트가 있는지 확인
        if ($tag->posts()->count() > 0) {
            return $this->businessErrorResponse(
                'HAS_POSTS',
                '포스트가 연결된 태그는 삭제할 수 없습니다'
            );
        }

        $tag->delete();

        return $this->deletedResponse('태그가 삭제되었습니다');
    }
}