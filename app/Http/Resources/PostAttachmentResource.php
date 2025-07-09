<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'original_name' => $this->original_name,
            'url' => $this->url,
            'full_url' => $this->full_url,
            'download_url' => route('api.attachments.download', $this->id),
            'size' => $this->size,
            'formatted_size' => $this->formatted_size,
            'mime_type' => $this->mime_type,
            'type' => $this->type,
            'icon' => $this->icon,
            'description' => $this->description,
            'download_count' => $this->download_count,
            'sort_order' => $this->sort_order,
            'is_public' => $this->is_public,
            'is_image' => $this->is_image,
            'is_document' => $this->is_document,
            
            // 상세 조회 시에만 포함
            'post' => $this->when($request->routeIs('api.attachments.show'), function () {
                return [
                    'id' => $this->post->id,
                    'title' => $this->post->title,
                    'slug' => $this->post->slug,
                ];
            }),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}