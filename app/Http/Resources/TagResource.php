<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            
            // 통계 정보
            'post_count' => $this->post_count ?? 0,
            
            // 관계 데이터
            'posts' => $this->when(
                $request->get('include_posts') && $this->relationLoaded('posts'),
                PostResource::collection($this->posts)
            ),
            
            // 관련 태그 (같은 포스트에 자주 함께 사용되는 태그들)
            'related_tags' => $this->when(
                $request->get('include_related'),
                function() {
                    return TagResource::collection(
                        $this->getRelatedTags(5)
                    );
                }
            ),
            
            // URL
            'urls' => [
                'self' => "/api/v1/tags/{$this->slug}",
                'posts' => "/api/v1/tags/{$this->slug}/posts",
            ],
            
            // SEO 메타
            'meta' => [
                'seo_title' => "'{$this->name}' 태그",
                'seo_description' => $this->description ?: "'{$this->name}' 태그가 포함된 글 목록입니다.",
                'color_class' => $this->getColorClass(),
            ],
            
            // 타임스탬프
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    /**
     * 관련 태그 조회 메소드
     */
    private function getRelatedTags(int $limit = 5)
    {
        // 이 태그와 함께 사용된 다른 태그들을 찾음
        return \App\Models\Tag::whereHas('posts', function($query) {
                $query->whereIn('id', $this->posts()->pluck('id'));
            })
            ->where('id', '!=', $this->id)
            ->withCount('posts')
            ->orderBy('posts_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 태그 색상에 따른 CSS 클래스 반환
     */
    private function getColorClass(): string
    {
        if (!$this->color) {
            return 'bg-gray-100 text-gray-800';
        }

        $colorMap = [
            '#ef4444' => 'bg-red-100 text-red-800',
            '#f97316' => 'bg-orange-100 text-orange-800',
            '#eab308' => 'bg-yellow-100 text-yellow-800',
            '#22c55e' => 'bg-green-100 text-green-800',
            '#3b82f6' => 'bg-blue-100 text-blue-800',
            '#8b5cf6' => 'bg-purple-100 text-purple-800',
            '#ec4899' => 'bg-pink-100 text-pink-800',
        ];

        return $colorMap[$this->color] ?? 'bg-gray-100 text-gray-800';
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
                'similar_tags' => "/api/v1/tags/{$this->id}/similar",
                'trending_posts' => "/api/v1/tags/{$this->id}/trending-posts",
            ],
        ];
    }
}