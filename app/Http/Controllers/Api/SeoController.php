<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SeoMeta;
use App\Services\SeoService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * SEO 메타 정보 관리 컨트롤러
 * 
 * @OA\Tag(
 *     name="SEO",
 *     description="SEO 메타 정보 관리 API"
 * )
 */
class SeoController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/seo/post/{id}",
     *     summary="포스트 SEO 메타 정보 조회",
     *     description="포스트의 SEO 메타 정보를 조회합니다",
     *     tags={"SEO"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SEO 메타 정보 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="SEO 메타 정보를 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="og_title", type="string", example="포스트 제목"),
     *                 @OA\Property(property="og_description", type="string", example="포스트 설명"),
     *                 @OA\Property(property="og_image", type="string", example="/storage/og-image.jpg"),
     *                 @OA\Property(property="meta_keywords", type="string", example="키워드1, 키워드2"),
     *                 @OA\Property(property="robots", type="string", example="index,follow"),
     *                 @OA\Property(property="canonical_url", type="string", example="https://example.com/post-slug"),
     *                 @OA\Property(property="twitter_card", type="string", example="summary_large_image"),
     *                 @OA\Property(property="custom_meta", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="포스트를 찾을 수 없음"
     *     )
     * )
     */
    public function getPostSeo(Post $post): JsonResponse
    {
        // 포스트 작성자 또는 관리자만 접근 가능
        if (!auth()->user()->isAdmin() && $post->user_id !== auth()->id()) {
            return $this->forbiddenResponse('SEO 정보에 접근할 권한이 없습니다');
        }

        $seoMeta = $post->seoMeta;
        
        if (!$seoMeta) {
            // SEO 메타가 없으면 기본값 생성
            $seoMeta = new SeoMeta([
                'post_id' => $post->id,
                'og_title' => $post->title,
                'og_description' => $post->summary ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 160),
                'meta_keywords' => $post->tags->pluck('name')->implode(', '),
            ]);
        }

        return $this->successResponse([
            'id' => $seoMeta->id,
            'post_id' => $post->id,
            'og_title' => $seoMeta->og_title,
            'og_description' => $seoMeta->og_description,
            'og_image' => $seoMeta->og_image,
            'og_type' => $seoMeta->og_type,
            'twitter_card' => $seoMeta->twitter_card,
            'twitter_title' => $seoMeta->twitter_title,
            'twitter_description' => $seoMeta->twitter_description,
            'twitter_image' => $seoMeta->twitter_image,
            'canonical_url' => $seoMeta->canonical_url,
            'meta_keywords' => $seoMeta->meta_keywords,
            'robots' => $seoMeta->robots,
            'custom_meta' => $seoMeta->custom_meta,
        ], 'SEO 메타 정보를 조회했습니다');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/seo/post/{id}",
     *     summary="포스트 SEO 메타 정보 생성/수정",
     *     description="포스트의 SEO 메타 정보를 생성하거나 수정합니다",
     *     tags={"SEO"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="포스트 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="og_title", type="string", example="커스텀 OG 제목"),
     *             @OA\Property(property="og_description", type="string", example="커스텀 OG 설명"),
     *             @OA\Property(property="og_image", type="string", example="/storage/custom-og.jpg"),
     *             @OA\Property(property="meta_keywords", type="string", example="키워드1, 키워드2, 키워드3"),
     *             @OA\Property(property="robots", type="string", example="index,follow"),
     *             @OA\Property(property="canonical_url", type="string", example="https://custom-domain.com/post"),
     *             @OA\Property(property="twitter_title", type="string", example="트위터 제목"),
     *             @OA\Property(property="twitter_description", type="string", example="트위터 설명"),
     *             @OA\Property(property="custom_meta", type="object", example={"author": "John Doe"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="SEO 메타 정보 저장 성공"
     *     )
     * )
     */
    public function updatePostSeo(Request $request, Post $post): JsonResponse
    {
        // 포스트 작성자 또는 관리자만 접근 가능
        if (!auth()->user()->isAdmin() && $post->user_id !== auth()->id()) {
            return $this->forbiddenResponse('SEO 정보를 수정할 권한이 없습니다');
        }

        $validator = Validator::make($request->all(), [
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:255',
            'og_type' => 'nullable|string|in:article,website,book,profile',
            'twitter_card' => 'nullable|string|in:summary,summary_large_image,app,player',
            'twitter_title' => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string|max:500',
            'twitter_image' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|url|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'robots' => 'nullable|string|max:100',
            'custom_meta' => 'nullable|array',
        ], [
            'og_title.max' => 'OG 제목은 255자를 초과할 수 없습니다',
            'og_description.max' => 'OG 설명은 500자를 초과할 수 없습니다',
            'canonical_url.url' => '올바른 URL 형식이 아닙니다',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            // SEO 메타 정보 업데이트 또는 생성
            $seoMeta = $post->seoMeta()->updateOrCreate(
                ['post_id' => $post->id],
                array_filter([
                    'og_title' => $request->og_title,
                    'og_description' => $request->og_description,
                    'og_image' => $request->og_image,
                    'og_type' => $request->og_type,
                    'twitter_card' => $request->twitter_card,
                    'twitter_title' => $request->twitter_title,
                    'twitter_description' => $request->twitter_description,
                    'twitter_image' => $request->twitter_image,
                    'canonical_url' => $request->canonical_url,
                    'meta_keywords' => $request->meta_keywords,
                    'robots' => $request->robots,
                    'custom_meta' => $request->custom_meta,
                ], function ($value) {
                    return $value !== null;
                })
            );

            return $this->successResponse($seoMeta, 'SEO 메타 정보가 저장되었습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse('SEO 메타 정보 저장 중 오류가 발생했습니다');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/seo/preview/{slug}",
     *     summary="포스트 SEO 미리보기",
     *     description="포스트의 SEO 메타 태그 미리보기를 생성합니다",
     *     tags={"SEO"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="포스트 슬러그",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SEO 미리보기 생성 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="SEO 미리보기가 생성되었습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="meta_tags", type="string", description="HTML 메타 태그"),
     *                 @OA\Property(property="json_ld", type="object", description="JSON-LD 구조화 데이터"),
     *                 @OA\Property(property="seo_data", type="object", description="SEO 데이터 객체"),
     *                 @OA\Property(
     *                     property="preview",
     *                     type="object",
     *                     @OA\Property(property="google_preview", type="object"),
     *                     @OA\Property(property="facebook_preview", type="object"),
     *                     @OA\Property(property="twitter_preview", type="object")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function previewSeo(string $slug): JsonResponse
    {
        $post = Post::with(['category', 'tags', 'user', 'seoMeta'])
            ->where('slug', $slug)
            ->first();

        if (!$post) {
            return $this->notFoundResponse('포스트를 찾을 수 없습니다');
        }

        // SEO 데이터 생성
        $seoData = SeoService::getPostSeoData($post);
        $jsonLd = SeoService::getJsonLd($post);
        $metaTags = SeoService::generateMetaTags($seoData);

        // 미리보기 데이터 생성
        $preview = [
            'google_preview' => [
                'title' => \Illuminate\Support\Str::limit($seoData['title'], 60),
                'description' => \Illuminate\Support\Str::limit($seoData['description'], 160),
                'url_display' => parse_url($seoData['url'], PHP_URL_HOST) . parse_url($seoData['url'], PHP_URL_PATH),
            ],
            'facebook_preview' => [
                'title' => $seoData['title'],
                'description' => \Illuminate\Support\Str::limit($seoData['description'], 200),
                'image' => $seoData['image'],
                'site_name' => config('app.name'),
            ],
            'twitter_preview' => [
                'title' => $seoData['title'],
                'description' => \Illuminate\Support\Str::limit($seoData['description'], 200),
                'image' => $seoData['image'],
                'card_type' => 'summary_large_image',
            ]
        ];

        return $this->successResponse([
            'meta_tags' => $metaTags,
            'json_ld' => $jsonLd,
            'seo_data' => $seoData,
            'preview' => $preview,
            'analysis' => [
                'title_length' => strlen($seoData['title']),
                'description_length' => strlen($seoData['description']),
                'keywords_count' => count(explode(',', $seoData['keywords'])),
                'has_image' => !empty($seoData['image']),
                'recommendations' => $this->generateSeoRecommendations($seoData)
            ]
        ], 'SEO 미리보기가 생성되었습니다');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/seo/sitemap",
     *     summary="사이트맵 데이터 조회",
     *     description="XML 사이트맵 생성을 위한 URL 데이터를 조회합니다",
     *     tags={"SEO"},
     *     @OA\Response(
     *         response=200,
     *         description="사이트맵 데이터 조회 성공"
     *     )
     * )
     */
    public function getSitemapData(): JsonResponse
    {
        try {
            $urls = SeoService::getSitemapUrls();

            return $this->successResponse([
                'urls' => $urls,
                'total_urls' => count($urls),
                'generated_at' => now()->toISOString()
            ], '사이트맵 데이터를 조회했습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse('사이트맵 데이터 조회 중 오류가 발생했습니다');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/seo/analyze",
     *     summary="SEO 분석",
     *     description="주어진 URL 또는 콘텐츠의 SEO를 분석합니다",
     *     tags={"SEO"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="페이지 제목"),
     *             @OA\Property(property="description", type="string", example="페이지 설명"),
     *             @OA\Property(property="content", type="string", example="페이지 내용"),
     *             @OA\Property(property="keywords", type="string", example="키워드1, 키워드2"),
     *             @OA\Property(property="url", type="string", example="https://example.com/page")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SEO 분석 완료"
     *     )
     * )
     */
    public function analyzeSeo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'keywords' => 'nullable|string',
            'url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $analysis = [
            'title' => [
                'text' => $request->title,
                'length' => strlen($request->title),
                'optimal' => strlen($request->title) >= 30 && strlen($request->title) <= 60,
                'score' => $this->scoreTitleLength(strlen($request->title))
            ],
            'description' => [
                'text' => $request->description ?: '',
                'length' => strlen($request->description ?: ''),
                'optimal' => strlen($request->description ?: '') >= 120 && strlen($request->description ?: '') <= 160,
                'score' => $this->scoreDescriptionLength(strlen($request->description ?: ''))
            ],
            'keywords' => [
                'text' => $request->keywords ?: '',
                'count' => $request->keywords ? count(explode(',', $request->keywords)) : 0,
                'optimal' => $request->keywords && count(explode(',', $request->keywords)) >= 3 && count(explode(',', $request->keywords)) <= 8,
            ],
            'content' => [
                'length' => strlen($request->content ?: ''),
                'word_count' => str_word_count(strip_tags($request->content ?: '')),
                'optimal' => str_word_count(strip_tags($request->content ?: '')) >= 300,
            ]
        ];

        $overallScore = ($analysis['title']['score'] + $analysis['description']['score']) / 2;
        
        $recommendations = [];
        if (!$analysis['title']['optimal']) {
            $recommendations[] = '제목을 30-60자 사이로 조정하세요';
        }
        if (!$analysis['description']['optimal']) {
            $recommendations[] = '설명을 120-160자 사이로 조정하세요';
        }
        if (!$analysis['keywords']['optimal']) {
            $recommendations[] = '키워드를 3-8개 정도로 설정하세요';
        }
        if (!$analysis['content']['optimal']) {
            $recommendations[] = '콘텐츠를 최소 300단어 이상으로 작성하세요';
        }

        return $this->successResponse([
            'analysis' => $analysis,
            'overall_score' => round($overallScore, 1),
            'grade' => $this->getGrade($overallScore),
            'recommendations' => $recommendations,
            'analyzed_at' => now()->toISOString()
        ], 'SEO 분석이 완료되었습니다');
    }

    /**
     * SEO 추천사항 생성
     */
    private function generateSeoRecommendations(array $seoData): array
    {
        $recommendations = [];

        if (strlen($seoData['title']) < 30) {
            $recommendations[] = '제목이 너무 짧습니다. 30자 이상으로 작성하세요.';
        } elseif (strlen($seoData['title']) > 60) {
            $recommendations[] = '제목이 너무 깁니다. 60자 이하로 줄이세요.';
        }

        if (strlen($seoData['description']) < 120) {
            $recommendations[] = '설명이 너무 짧습니다. 120자 이상으로 작성하세요.';
        } elseif (strlen($seoData['description']) > 160) {
            $recommendations[] = '설명이 너무 깁니다. 160자 이하로 줄이세요.';
        }

        if (empty($seoData['keywords'])) {
            $recommendations[] = '메타 키워드를 추가하세요.';
        }

        if (empty($seoData['image'])) {
            $recommendations[] = 'OG 이미지를 추가하세요.';
        }

        return $recommendations;
    }

    /**
     * 제목 길이 점수 계산
     */
    private function scoreTitleLength(int $length): float
    {
        if ($length >= 30 && $length <= 60) {
            return 10.0;
        } elseif ($length >= 20 && $length < 30) {
            return 7.0;
        } elseif ($length > 60 && $length <= 70) {
            return 7.0;
        } else {
            return 3.0;
        }
    }

    /**
     * 설명 길이 점수 계산
     */
    private function scoreDescriptionLength(int $length): float
    {
        if ($length >= 120 && $length <= 160) {
            return 10.0;
        } elseif ($length >= 100 && $length < 120) {
            return 7.0;
        } elseif ($length > 160 && $length <= 180) {
            return 7.0;
        } else {
            return 3.0;
        }
    }

    /**
     * 점수에 따른 등급 결정
     */
    private function getGrade(float $score): string
    {
        if ($score >= 9) return 'A';
        elseif ($score >= 7) return 'B';
        elseif ($score >= 5) return 'C';
        else return 'D';
    }
}