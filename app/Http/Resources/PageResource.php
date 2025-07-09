<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->when(
                $request->route()->getName() === 'api.pages.show' || $request->get('include_content'),
                $this->content
            ),
            'template' => $this->template,
            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,
            'show_in_menu' => $this->show_in_menu,
            'menu_title' => $this->menu_title ?: $this->title,
            
            // 이미지
            'featured_image' => $this->featured_image ? [
                'url' => asset('storage/' . $this->featured_image),
                'path' => $this->featured_image,
            ] : null,
            
            // 페이지 설정
            'custom_css' => $this->when(
                $request->route()->getName() === 'api.pages.show',
                $this->custom_css
            ),
            'custom_js' => $this->when(
                $request->route()->getName() === 'api.pages.show',
                $this->custom_js
            ),
            
            // 계층 구조
            'parent_id' => $this->parent_id,
            'parent' => new PageResource($this->whenLoaded('parent')),
            'children' => PageResource::collection($this->whenLoaded('children')),
            'ancestors' => PageResource::collection($this->whenLoaded('ancestors')),
            
            // 관계 데이터
            'category' => new CategoryResource($this->whenLoaded('category')),
            'author' => $this->whenLoaded('user', function() {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            
            // SEO 메타 데이터
            'seo_meta' => $this->when(
                $request->route()->getName() === 'api.pages.show' && $this->relationLoaded('seoMeta'),
                new SeoMetaResource($this->seoMeta)
            ),
            'meta_title' => $this->meta_title ?: $this->title,
            'meta_description' => $this->meta_description ?: $this->excerpt,
            'meta_keywords' => $this->meta_keywords,
            
            // 통계
            'views_count' => $this->views_count ?? 0,
            'reading_time' => $this->content ? ceil(str_word_count(strip_tags($this->content)) / 200) : 0,
            
            // URL
            'urls' => [
                'self' => url("/api/v1/pages/{$this->slug}"),
            ],
            
            // 내비게이션 정보
            'navigation' => $this->when(
                $this->show_in_menu,
                [
                    'menu_title' => $this->menu_title ?: $this->title,
                    'menu_order' => $this->sort_order,
                    'has_children' => $this->whenLoaded('children', function() {
                        return $this->children->count() > 0;
                    }, false),
                    'breadcrumb_trail' => $this->when(
                        $this->relationLoaded('ancestors') && $this->ancestors,
                        $this->ancestors ? $this->ancestors->pluck('title')->push($this->title)->implode(' > ') : $this->title
                    ),
                ]
            ),
            
            // 메타데이터
            'meta' => [
                'word_count' => str_word_count(strip_tags($this->content)),
                'character_count' => mb_strlen(strip_tags($this->content)),
                'template_info' => $this->getTemplateInfo(),
                'last_modified' => $this->updated_at->diffForHumans(),
            ],
            
            // 타임스탬프
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'published_at' => $this->when(
                $this->is_published,
                $this->created_at->toISOString()
            ),
        ];
    }

    /**
     * 읽기 시간 계산
     */
    private function getReadingTime(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        $readingSpeed = 200; // 분당 평균 단어 수
        return max(1, ceil($wordCount / $readingSpeed));
    }

    /**
     * 템플릿 정보 반환
     */
    private function getTemplateInfo(): array
    {
        $templates = [
            'default' => ['name' => '기본 템플릿', 'description' => '일반적인 페이지 레이아웃'],
            'full-width' => ['name' => '전체 폭', 'description' => '사이드바 없는 전체 폭 레이아웃'],
            'landing' => ['name' => '랜딩 페이지', 'description' => '마케팅용 랜딩 페이지 레이아웃'],
            'contact' => ['name' => '연락처', 'description' => '연락처 정보 및 폼이 포함된 레이아웃'],
            'about' => ['name' => '소개', 'description' => '회사/개인 소개 페이지 레이아웃'],
        ];

        return $templates[$this->template] ?? [
            'name' => '사용자 정의',
            'description' => '사용자가 정의한 템플릿'
        ];
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
            'links' => [
                'siblings' => $this->parent_id ? url("/api/v1/pages/{$this->id}/siblings") : null,
                'children' => url("/api/v1/pages/{$this->id}/children"),
                'related_pages' => url("/api/v1/pages/{$this->id}/related"),
            ],
        ];
    }
}