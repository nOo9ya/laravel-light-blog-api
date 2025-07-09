<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SeoMetaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            
            // 기본 SEO 메타 태그
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'meta_robots' => $this->meta_robots ?? 'index,follow',
            'canonical_url' => $this->canonical_url,
            
            // Open Graph 메타 태그
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image ? [
                'url' => asset('storage/' . $this->og_image),
                'path' => $this->og_image,
                'width' => 1200,
                'height' => 630,
                'alt' => $this->og_title ?: $this->meta_title,
            ] : null,
            'og_type' => $this->og_type ?? 'article',
            'og_url' => $this->og_url,
            'og_site_name' => $this->og_site_name ?? config('app.name'),
            'og_locale' => $this->og_locale ?? 'ko_KR',
            
            // Twitter Card 메타 태그
            'twitter_card' => $this->twitter_card ?? 'summary_large_image',
            'twitter_title' => $this->twitter_title ?: $this->og_title,
            'twitter_description' => $this->twitter_description ?: $this->og_description,
            'twitter_image' => $this->twitter_image ? [
                'url' => asset('storage/' . $this->twitter_image),
                'path' => $this->twitter_image,
                'alt' => $this->twitter_title ?: $this->og_title,
            ] : ($this->og_image ? [
                'url' => asset('storage/' . $this->og_image),
                'path' => $this->og_image,
                'alt' => $this->twitter_title ?: $this->og_title,
            ] : null),
            'twitter_site' => $this->twitter_site,
            'twitter_creator' => $this->twitter_creator,
            
            // JSON-LD 구조화 데이터
            'schema_type' => $this->schema_type ?? 'Article',
            'schema_data' => $this->schema_data ? json_decode($this->schema_data, true) : null,
            
            // 추가 메타 정보
            'focus_keyword' => $this->focus_keyword,
            'readability_score' => $this->readability_score,
            'seo_score' => $this->seo_score,
            
            // 분석 및 권장사항
            'seo_analysis' => $this->when(
                $request->get('include_analysis'),
                $this->getSeoAnalysis()
            ),
            
            // 소유자 정보
            'seoable_type' => $this->seoable_type,
            'seoable_id' => $this->seoable_id,
            'seoable' => $this->when(
                $this->relationLoaded('seoable'),
                function() {
                    switch($this->seoable_type) {
                        case 'App\\Models\\Post':
                            return new PostResource($this->seoable);
                        case 'App\\Models\\Page':
                            return new PageResource($this->seoable);
                        case 'App\\Models\\Category':
                            return new CategoryResource($this->seoable);
                        default:
                            return $this->seoable;
                    }
                }
            ),
            
            // 타임스탬프
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    /**
     * SEO 분석 정보 반환
     */
    private function getSeoAnalysis(): array
    {
        $analysis = [
            'title_length' => [
                'value' => mb_strlen($this->meta_title ?? ''),
                'status' => $this->getTitleLengthStatus(),
                'recommendation' => $this->getTitleRecommendation(),
            ],
            'description_length' => [
                'value' => mb_strlen($this->meta_description ?? ''),
                'status' => $this->getDescriptionLengthStatus(),
                'recommendation' => $this->getDescriptionRecommendation(),
            ],
            'has_focus_keyword' => [
                'value' => !empty($this->focus_keyword),
                'status' => !empty($this->focus_keyword) ? 'good' : 'warning',
                'recommendation' => !empty($this->focus_keyword) ? '포커스 키워드가 설정되었습니다.' : '포커스 키워드를 설정해주세요.',
            ],
            'has_og_image' => [
                'value' => !empty($this->og_image),
                'status' => !empty($this->og_image) ? 'good' : 'warning',
                'recommendation' => !empty($this->og_image) ? 'Open Graph 이미지가 설정되었습니다.' : 'Open Graph 이미지를 설정해주세요.',
            ],
        ];

        // 전체 점수 계산
        $scores = collect($analysis)->pluck('status');
        $goodCount = $scores->filter(fn($status) => $status === 'good')->count();
        $totalCount = $scores->count();
        
        $analysis['overall_score'] = [
            'percentage' => round(($goodCount / $totalCount) * 100),
            'status' => $goodCount >= 3 ? 'good' : ($goodCount >= 2 ? 'warning' : 'error'),
        ];

        return $analysis;
    }

    private function getTitleLengthStatus(): string
    {
        $length = mb_strlen($this->meta_title ?? '');
        if ($length >= 30 && $length <= 60) return 'good';
        if ($length >= 20 && $length <= 70) return 'warning';
        return 'error';
    }

    private function getTitleRecommendation(): string
    {
        $length = mb_strlen($this->meta_title ?? '');
        if ($length < 20) return '제목이 너무 짧습니다. 30-60자 사이로 작성해주세요.';
        if ($length > 70) return '제목이 너무 깁니다. 30-60자 사이로 작성해주세요.';
        if ($length >= 30 && $length <= 60) return '제목 길이가 적절합니다.';
        return '제목 길이를 30-60자 사이로 조정해주세요.';
    }

    private function getDescriptionLengthStatus(): string
    {
        $length = mb_strlen($this->meta_description ?? '');
        if ($length >= 120 && $length <= 160) return 'good';
        if ($length >= 100 && $length <= 180) return 'warning';
        return 'error';
    }

    private function getDescriptionRecommendation(): string
    {
        $length = mb_strlen($this->meta_description ?? '');
        if ($length < 100) return '설명이 너무 짧습니다. 120-160자 사이로 작성해주세요.';
        if ($length > 180) return '설명이 너무 깁니다. 120-160자 사이로 작성해주세요.';
        if ($length >= 120 && $length <= 160) return '설명 길이가 적절합니다.';
        return '설명 길이를 120-160자 사이로 조정해주세요.';
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [
            'meta' => [
                'seo_tools' => [
                    'google_search_console' => $this->canonical_url ? "https://search.google.com/search-console?resource_id={$this->canonical_url}" : null,
                    'facebook_debugger' => $this->og_url ? "https://developers.facebook.com/tools/debug/?q={$this->og_url}" : null,
                    'twitter_validator' => $this->og_url ? "https://cards-dev.twitter.com/validator" : null,
                ],
            ],
        ];
    }
}