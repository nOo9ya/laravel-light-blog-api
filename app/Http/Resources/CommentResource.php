<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
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
            'content' => $this->content,
            'status' => $this->status,
            'spam_score' => $this->when(
                auth()->check() && auth()->user()->hasRole('admin'),
                $this->spam_score
            ),
            
            // 작성자 정보
            'author' => [
                'name' => $this->author_name,
                'email' => $this->when(
                    auth()->check() && auth()->user()->hasRole('admin'),
                    $this->author_email
                ),
                'website' => $this->author_website,
                'is_registered' => !is_null($this->user_id),
                'avatar' => $this->getAuthorAvatar(),
            ],
            
            // 등록 사용자 정보 (있는 경우)
            'user' => $this->when(
                $this->user_id && $this->relationLoaded('user'),
                function() {
                    return [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                        'avatar' => $this->user->avatar ? asset('storage/' . $this->user->avatar) : null,
                        'role' => $this->user->role,
                    ];
                }
            ),
            
            // 계층 구조
            'parent_id' => $this->parent_id,
            'parent' => new CommentResource($this->whenLoaded('parent')),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'replies_count' => $this->when(
                isset($this->replies_count),
                $this->replies_count
            ),
            'depth' => $this->getDepth(),
            
            // 포스트 정보
            'post' => $this->when(
                $this->relationLoaded('post'),
                [
                    'id' => $this->post->id,
                    'title' => $this->post->title,
                    'slug' => $this->post->slug,
                    'url' => route('posts.show', $this->post->slug),
                ]
            ),
            
            // 좋아요/반대 (확장 가능)
            'likes_count' => 0, // 향후 구현
            'dislikes_count' => 0, // 향후 구현
            'user_liked' => false, // 향후 구현
            
            // 관리자용 정보
            'admin_info' => $this->when(
                auth()->check() && auth()->user()->hasRole('admin'),
                [
                    'ip_address' => $this->ip_address,
                    'user_agent' => $this->user_agent,
                    'referer' => $this->referer,
                    'spam_reasons' => $this->getSpamReasons(),
                ]
            ),
            
            // URL
            'urls' => [
                'permalink' => route('posts.show', $this->post->slug) . '#comment-' . $this->id,
                'reply' => route('comments.reply', $this->id),
                'report' => route('comments.report', $this->id),
                'edit' => $this->when(
                    $this->canEdit(),
                    route('comments.edit', $this->id)
                ),
                'delete' => $this->when(
                    $this->canDelete(),
                    route('comments.delete', $this->id)
                ),
            ],
            
            // 권한
            'permissions' => [
                'can_edit' => $this->canEdit(),
                'can_delete' => $this->canDelete(),
                'can_reply' => $this->canReply(),
                'can_report' => $this->canReport(),
            ],
            
            // 메타데이터
            'meta' => [
                'word_count' => str_word_count($this->content),
                'character_count' => mb_strlen($this->content),
                'has_links' => $this->hasLinks(),
                'time_ago' => $this->created_at->diffForHumans(),
                'is_edited' => $this->updated_at->gt($this->created_at->addMinutes(5)),
            ],
            
            // 타임스탬프
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    /**
     * 작성자 아바타 반환
     */
    private function getAuthorAvatar(): ?string
    {
        if ($this->user_id && $this->user && $this->user->avatar) {
            return asset('storage/' . $this->user->avatar);
        }

        // Gravatar 사용
        $email = $this->author_email;
        if ($email) {
            $hash = md5(strtolower(trim($email)));
            return "https://www.gravatar.com/avatar/{$hash}?s=80&d=mp&r=g";
        }

        return null;
    }

    /**
     * 댓글 깊이 계산
     */
    private function getDepth(): int
    {
        $depth = 0;
        $comment = $this;
        
        while ($comment->parent_id) {
            $depth++;
            $comment = $comment->parent;
            if (!$comment || $depth > 10) break; // 무한 루프 방지
        }
        
        return $depth;
    }

    /**
     * 스팸 판정 이유 반환
     */
    private function getSpamReasons(): array
    {
        $reasons = [];
        
        if ($this->spam_score > 50) {
            if (substr_count(strtolower($this->content), 'http') > 2) {
                $reasons[] = '과도한 링크 포함';
            }
            
            if (preg_match('/[!@#$%^&*()]{3,}/', $this->content)) {
                $reasons[] = '과도한 특수문자 사용';
            }
            
            if (mb_strlen($this->content) < 10) {
                $reasons[] = '내용이 너무 짧음';
            }
            
            if (preg_match('/(.)\1{4,}/', $this->content)) {
                $reasons[] = '반복 문자 과다';
            }
        }
        
        return $reasons;
    }

    /**
     * 링크 포함 여부 확인
     */
    private function hasLinks(): bool
    {
        return preg_match('/https?:\/\//', $this->content) > 0;
    }

    /**
     * 편집 권한 확인
     */
    private function canEdit(): bool
    {
        if (!auth()->check()) return false;
        
        $user = auth()->user();
        
        // 관리자는 모든 댓글 편집 가능
        if ($user->hasRole('admin')) return true;
        
        // 작성자는 자신의 댓글만 편집 가능 (등록 사용자인 경우)
        if ($this->user_id && $this->user_id === $user->id) {
            // 24시간 이내에만 편집 가능
            return $this->created_at->gt(now()->subHours(24));
        }
        
        return false;
    }

    /**
     * 삭제 권한 확인
     */
    private function canDelete(): bool
    {
        if (!auth()->check()) return false;
        
        $user = auth()->user();
        
        // 관리자는 모든 댓글 삭제 가능
        if ($user->hasRole('admin')) return true;
        
        // 작성자는 자신의 댓글만 삭제 가능 (등록 사용자인 경우)
        if ($this->user_id && $this->user_id === $user->id) {
            return true;
        }
        
        return false;
    }

    /**
     * 답글 권한 확인
     */
    private function canReply(): bool
    {
        // 댓글 시스템이 활성화되어 있고, 너무 깊지 않은 경우
        return $this->getDepth() < 5 && $this->status === 'approved';
    }

    /**
     * 신고 권한 확인
     */
    private function canReport(): bool
    {
        return auth()->check() && $this->status === 'approved';
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
                'thread' => route('api.comments.thread', $this->id),
                'siblings' => $this->parent_id ? route('api.comments.siblings', $this->parent_id) : null,
            ],
        ];
    }
}