<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            'summary' => $this->summary,
            'content' => $this->when(
                $request->get('include_content') || str_contains($request->path(), '/posts/'),
                $this->content
            ),
            'timeline_json' => $this->when(
                str_contains($request->path(), '/posts/'),
                $this->timeline_json ? json_decode($this->timeline_json, true) : null
            ),
            'main_image' => $this->main_image ? [
                'url' => asset('storage/' . $this->main_image),
                'path' => $this->main_image,
            ] : null,
            'og_image' => $this->og_image ? [
                'url' => asset('storage/' . $this->og_image),
                'path' => $this->og_image,
            ] : null,
            'status' => $this->status,
            'published_at' => $this->published_at?->toISOString(),
            'views_count' => $this->views_count,
            'reading_time' => $this->reading_time,
            
            // 관계 데이터
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            
            // SEO 메타 데이터 (상세 페이지에서만)
            'seo_meta' => $this->when(
                str_contains($request->path(), '/posts/') && $this->relationLoaded('seoMeta'),
                new SeoMetaResource($this->seoMeta)
            ),
            
            // URL
            'urls' => [
                'self' => url("/api/v1/posts/{$this->slug}"),
                'web' => url("/posts/{$this->slug}"),
                'edit' => $this->when(
                    auth()->check() && (auth()->user()->hasRole('admin') || auth()->id() === $this->user_id),
                    url("/admin/posts/{$this->id}/edit")
                ),
            ],
            
            // 타임스탬프
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // 추가 메타데이터
            'meta' => [
                'word_count' => str_word_count(strip_tags($this->content)),
                'character_count' => mb_strlen(strip_tags($this->content)),
                'excerpt' => $this->when(
                    !$this->summary,
                    \Str::limit(strip_tags($this->content), 200)
                ),
            ],
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
                'related_posts' => route('api.posts.related', $this->id),
                'comments' => route('api.comments.index', $this->id),
            ],
        ];
    }
}