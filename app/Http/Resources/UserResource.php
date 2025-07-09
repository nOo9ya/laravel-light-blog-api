<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends ApiResource
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // 카운트 정보 (withCount로 로드된 경우)
            'posts_count' => $this->when(isset($this->posts_count), $this->posts_count),
            'comments_count' => $this->when(isset($this->comments_count), $this->comments_count),
            'pages_count' => $this->when(isset($this->pages_count), $this->pages_count),
        ];
    }

    /**
     * 응답 메시지 커스터마이즈
     */
    protected function getMessage(): string
    {
        return '사용자 정보를 조회했습니다';
    }
}