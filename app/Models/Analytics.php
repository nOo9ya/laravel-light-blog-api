<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Analytics extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | 모델 속성 (Attributes & Properties)
    |--------------------------------------------------------------------------
    */
    // region --- 모델 속성 ---
    protected $fillable = [
        'post_id',
        'user_id',
        'type',
        'ip_address',
        'user_agent',
        'referer',
        'session_id',
        'search_query',
        'search_results_count',
        'search_type',
        'page_url',
        'page_title',
        'browser',
        'browser_version',
        'platform',
        'device_type',
        'country',
        'city',
        'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'search_results_count' => 'integer',
    ];
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
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 스코프 (Scopes)
    |--------------------------------------------------------------------------
    */
    // region --- 스코프 ---
    public function scopePageViews($query)
    {
        return $query->where('type', 'page_view');
    }

    public function scopeSearches($query)
    {
        return $query->where('type', 'search');
    }

    public function scopeDaily($query, $date = null)
    {
        $date = $date ?: now()->format('Y-m-d');
        return $query->whereDate('created_at', $date);
    }

    public function scopeWeekly($query, $startDate = null)
    {
        $startDate = $startDate ?: now()->startOfWeek();
        return $query->whereBetween('created_at', [
            $startDate,
            $startDate->copy()->endOfWeek()
        ]);
    }

    public function scopeMonthly($query, $month = null, $year = null)
    {
        $month = $month ?: now()->month;
        $year = $year ?: now()->year;
        
        return $query->whereMonth('created_at', $month)
                    ->whereYear('created_at', $year);
    }

    public function scopeByPost($query, $postId)
    {
        return $query->where('post_id', $postId);
    }

    public function scopeUniqueVisitors($query)
    {
        return $query->select('ip_address')->distinct();
    }

    public function scopeRefererStats($query)
    {
        return $query->selectRaw('referer, COUNT(*) as count')
                    ->whereNotNull('referer')
                    ->groupBy('referer')
                    ->orderBy('count', 'desc');
    }

    public function scopeBrowserStats($query)
    {
        return $query->selectRaw('browser, COUNT(*) as count')
                    ->whereNotNull('browser')
                    ->groupBy('browser')
                    ->orderBy('count', 'desc');
    }

    public function scopePopularPages($query, $limit = 10)
    {
        return $query->with('post')
                    ->selectRaw('post_id, COUNT(*) as views')
                    ->whereNotNull('post_id')
                    ->groupBy('post_id')
                    ->orderBy('views', 'desc')
                    ->limit($limit);
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 기타 메서드 (Additional Methods)
    |--------------------------------------------------------------------------
    */
    // region --- 기타 메서드 ---
    
    /**
     * 접속 기록 생성 (중복 방지)
     */
    public static function recordPageView(Post $post, $request, $user = null): ?self
    {
        $ipAddress = $request->ip();
        $sessionId = $request->session()->getId();
        
        // 중복 방문 체크 (같은 IP, 같은 포스트, 1시간 이내)
        $existing = static::where('post_id', $post->id)
            ->where('ip_address', $ipAddress)
            ->where('type', 'page_view')
            ->where('created_at', '>=', now()->subHour())
            ->first();
            
        if ($existing) {
            return null; // 중복 방문
        }
        
        // User-Agent 파싱
        $userAgent = $request->userAgent();
        $browserInfo = static::parseUserAgent($userAgent);
        
        return static::create([
            'post_id' => $post->id,
            'user_id' => $user?->id,
            'type' => 'page_view',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer' => $request->header('referer'),
            'session_id' => $sessionId,
            'page_url' => $request->fullUrl(),
            'page_title' => $post->title,
            'browser' => $browserInfo['browser'],
            'browser_version' => $browserInfo['version'],
            'platform' => $browserInfo['platform'],
            'device_type' => $browserInfo['device_type'],
        ]);
    }
    
    /**
     * 검색 기록 생성
     */
    public static function recordSearch(string $query, int $resultsCount, string $type, $request, $user = null): self
    {
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();
        $browserInfo = static::parseUserAgent($userAgent);
        
        return static::create([
            'user_id' => $user?->id,
            'type' => 'search',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer' => $request->header('referer'),
            'session_id' => $request->session()->getId(),
            'search_query' => $query,
            'search_results_count' => $resultsCount,
            'search_type' => $type,
            'page_url' => $request->fullUrl(),
            'browser' => $browserInfo['browser'],
            'browser_version' => $browserInfo['version'],
            'platform' => $browserInfo['platform'],
            'device_type' => $browserInfo['device_type'],
        ]);
    }
    
    /**
     * User-Agent 파싱
     */
    protected static function parseUserAgent(string $userAgent): array
    {
        $browser = 'Unknown';
        $version = '';
        $platform = 'Unknown';
        $deviceType = 'desktop';
        
        // 브라우저 감지
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome';
            $version = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox';
            $version = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Safari';
            $version = $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Edge';
            $version = $matches[1];
        }
        
        // 플랫폼 감지
        if (strpos($userAgent, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (strpos($userAgent, 'Macintosh') !== false) {
            $platform = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $platform = 'Android';
            $deviceType = 'mobile';
        } elseif (strpos($userAgent, 'iPhone') !== false) {
            $platform = 'iOS';
            $deviceType = 'mobile';
        } elseif (strpos($userAgent, 'iPad') !== false) {
            $platform = 'iOS';
            $deviceType = 'tablet';
        }
        
        return [
            'browser' => $browser,
            'version' => $version,
            'platform' => $platform,
            'device_type' => $deviceType,
        ];
    }
    
    /**
     * 일일 통계 요약
     */
    public static function getDailySummary($date = null): array
    {
        $date = $date ?: now()->format('Y-m-d');
        
        $totalViews = static::daily($date)->pageViews()->count();
        $uniqueVisitors = static::daily($date)->pageViews()->distinct('ip_address')->count();
        $totalSearches = static::daily($date)->searches()->count();
        $popularPages = static::daily($date)->popularPages(5)->get();
        
        return [
            'date' => $date,
            'total_views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'total_searches' => $totalSearches,
            'popular_pages' => $popularPages,
        ];
    }
    
    /**
     * 주간 통계 요약
     */
    public static function getWeeklySummary($startDate = null): array
    {
        $startDate = $startDate ?: now()->startOfWeek();
        
        $totalViews = static::weekly($startDate)->pageViews()->count();
        $uniqueVisitors = static::weekly($startDate)->pageViews()->distinct('ip_address')->count();
        $totalSearches = static::weekly($startDate)->searches()->count();
        
        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->endOfWeek()->format('Y-m-d'),
            'total_views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'total_searches' => $totalSearches,
        ];
    }
    // endregion
}