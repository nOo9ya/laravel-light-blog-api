<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Comment extends Model
{
    /*
    |--------------------------------------------------------------------------
    | 모델 속성 (Attributes & Properties)
    |--------------------------------------------------------------------------
    */
    // region --- 모델 속성 ---
    protected $fillable = [
        'post_id',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_password',
        'parent_id',
        'depth',
        'path',
        'content',
        'content_html',
        'status',
        'ip_address',
        'user_agent',
        'spam_score',
        'detected_links',
        'og_data',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'spam_score' => 'array',
        'detected_links' => 'array',
        'og_data' => 'array',
        'approved_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'depth' => 0,
    ];

    protected $hidden = [
        'guest_password',
        'ip_address',
        'user_agent',
        'spam_score',
    ];
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 모델 이벤트 (Events)
    |--------------------------------------------------------------------------
    */
    // region --- 모델 이벤트 ---
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Comment $comment) {
            // 회원 댓글은 자동 승인
            if ($comment->user_id) {
                $comment->status = 'approved';
            }

            // 비회원 비밀번호 해시
            if ($comment->guest_password) {
                $comment->guest_password = Hash::make($comment->guest_password);
            }

            // IP 주소 자동 설정
            if (!$comment->ip_address) {
                $comment->ip_address = request()->ip();
            }

            // User Agent 자동 설정
            if (!$comment->user_agent) {
                $comment->user_agent = request()->userAgent();
            }

            // 계층 구조 설정
            if ($comment->parent_id) {
                $parent = static::find($comment->parent_id);
                if ($parent) {
                    $comment->depth = $parent->depth + 1;
                    $comment->path = $parent->path . '/' . $parent->id;
                }
            } else {
                $comment->path = '';
            }

            // 링크 감지 및 HTML 변환
            $comment->processContent();
        });

        static::updating(function (Comment $comment) {
            // 내용이 변경되면 재처리
            if ($comment->isDirty('content')) {
                $comment->processContent();
            }
        });
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 관계 (Relationships)
    |--------------------------------------------------------------------------
    */
    // region --- 관계 ---
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->with(['user', 'replies'])
            ->approved()
            ->orderBy('created_at');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 스코프 (Scopes)
    |--------------------------------------------------------------------------
    */
    // region --- 스코프 ---
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSpam($query)
    {
        return $query->where('status', 'spam');
    }

    public function scopeTopLevel($query)
    {
        return $query->where('parent_id', null);
    }

    public function scopeReplies($query)
    {
        return $query->where('parent_id', '!=', null);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeOldest($query)
    {
        return $query->orderBy('created_at', 'asc');
    }

    public function scopeByPost($query, $postId)
    {
        return $query->where('post_id', $postId);
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 접근자/변경자 (Accessors & Mutators)
    |--------------------------------------------------------------------------
    */
    // region --- 접근자/변경자 ---
    public function getAuthorNameAttribute(): string
    {
        return $this->user ? $this->user->name : ($this->guest_name ?: 'Anonymous');
    }

    public function getAuthorEmailAttribute(): ?string
    {
        return $this->user ? $this->user->email : $this->guest_email;
    }

    public function getIsGuestAttribute(): bool
    {
        return !$this->user_id;
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'approved';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsSpamAttribute(): bool
    {
        return $this->status === 'spam';
    }

    public function getHasRepliesAttribute(): bool
    {
        return $this->children()->approved()->exists();
    }

    public function getRepliesCountAttribute(): int
    {
        return $this->children()->approved()->count();
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 기타 메서드 (Additional Methods)
    |--------------------------------------------------------------------------
    */
    // region --- 기타 메서드 ---
    public function approve(?User $approvedBy = null): bool
    {
        return $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $approvedBy?->id ?: auth()->id(),
        ]);
    }

    public function markAsSpam(): bool
    {
        return $this->update(['status' => 'spam']);
    }

    public function softDelete(): bool
    {
        return $this->update(['status' => 'deleted']);
    }

    public function verifyGuestPassword(string $password): bool
    {
        return $this->is_guest && Hash::check($password, $this->guest_password);
    }

    protected function processContent(): void
    {
        // 링크 감지
        $this->detectLinks();
        
        // HTML 변환 (링크 자동 변환, 줄바꿈 처리)
        $this->content_html = $this->convertToHtml($this->content);
        
        // 스팸 점수 계산
        $this->calculateSpamScore();
    }

    protected function detectLinks(): void
    {
        $pattern = '/https?:\/\/[^\s<>"\']+/i';
        preg_match_all($pattern, $this->content, $matches);
        
        $links = array_unique($matches[0]);
        $this->detected_links = $links;
        
        // OG 데이터 추출 (첫 번째 링크만)
        if (!empty($links)) {
            $this->extractOgData($links[0]);
        }
    }

    protected function extractOgData(string $url): void
    {
        try {
            // URL 검증 및 보안 강화
            if (!filter_var($url, FILTER_VALIDATE_URL) || 
                !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])) {
                $this->og_data = null;
                return;
            }

            // 로컬 IP 및 사설 IP 차단
            $host = parse_url($url, PHP_URL_HOST);
            $ip = gethostbyname($host);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                $this->og_data = null;
                return;
            }

            // HTTP 클라이언트 설정 (보안 강화)
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'max_redirects' => 3,
                    'user_agent' => 'Laravel Light Blog OG Parser/1.0',
                    'header' => [
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language: en-US,en;q=0.5',
                        'Accept-Encoding: gzip, deflate',
                        'Connection: close',
                    ]
                ]
            ]);

            $html = @file_get_contents($url, false, $context, 0, 1024 * 50); // 50KB 제한
            
            if ($html) {
                $ogData = [];
                
                // OG 제목 추출 (XSS 방지)
                if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
                    $ogData['title'] = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8');
                }
                
                // OG 설명 추출 (XSS 방지)
                if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
                    $ogData['description'] = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8');
                }
                
                // OG 이미지 추출 (URL 검증)
                if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
                    $imageUrl = trim($matches[1]);
                    if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                        $ogData['image'] = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
                    }
                }
                
                $this->og_data = !empty($ogData) ? $ogData : null;
            }
        } catch (\Exception $e) {
            // OG 데이터 추출 실패 시 무시
            $this->og_data = null;
        }
    }

    protected function convertToHtml(string $content): string
    {
        // HTML 이스케이프
        $html = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        
        // 줄바꿈을 <br>로 변환
        $html = nl2br($html);
        
        // 링크 자동 변환 (보안 강화)
        $pattern = '/https?:\/\/[^\s<>"\']+/i';
        $html = preg_replace_callback($pattern, function($matches) {
            $url = $matches[0];
            
            // URL 검증
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
            
            // 위험한 스킴 차단
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'])) {
                return $url;
            }
            
            // 로컬 IP 차단
            $host = parse_url($url, PHP_URL_HOST);
            if ($host) {
                $ip = gethostbyname($host);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return $url;
                }
            }
            
            // 안전한 링크로 변환
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $displayUrl = strlen($url) > 50 ? substr($url, 0, 47) . '...' : $url;
            $safeDisplayUrl = htmlspecialchars($displayUrl, ENT_QUOTES, 'UTF-8');
            
            return '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer nofollow">' . $safeDisplayUrl . '</a>';
        }, $html);
        
        return $html;
    }

    protected function calculateSpamScore(): void
    {
        // SpamFilterService 사용
        $spamData = \App\Services\SpamFilterService::calculateSpamScore($this);
        
        $this->spam_score = $spamData;
        
        // 자동 조치
        if ($spamData['is_spam']) {
            $this->status = 'spam';
        } elseif ($spamData['needs_moderation'] && $this->status === 'approved') {
            $this->status = 'pending';
        }
    }
    // endregion
}
