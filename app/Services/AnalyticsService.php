<?php

namespace App\Services;

use App\Models\Analytics;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * 대시보드용 통계 데이터 조회
     */
    public function getDashboardStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $lastWeek = Carbon::today()->subDays(7);
        $lastMonth = Carbon::today()->subDays(30);

        return [
            // 방문자 통계
            'today_visitors' => $this->getUniqueVisitors($today),
            'yesterday_visitors' => $this->getUniqueVisitors($yesterday, $yesterday->copy()->endOfDay()),
            'week_visitors' => $this->getUniqueVisitors($lastWeek),
            'month_visitors' => $this->getUniqueVisitors($lastMonth),
            
            // 페이지뷰 통계
            'today_page_views' => $this->getPageViews($today),
            'yesterday_page_views' => $this->getPageViews($yesterday, $yesterday->copy()->endOfDay()),
            'week_page_views' => $this->getPageViews($lastWeek),
            'month_page_views' => $this->getPageViews($lastMonth),
            'total_page_views' => Analytics::where('type', 'page_view')->count(),
            
            // 인기 콘텐츠
            'popular_posts' => $this->getPopularPosts(),
            'popular_searches' => $this->getPopularSearches(),
            'recent_searches' => $this->getRecentSearches(),
            
            // 기술 통계
            'browser_stats' => $this->getBrowserStats(),
            'device_stats' => $this->getDeviceStats(),
            'platform_stats' => $this->getPlatformStats(),
            
            // 트렌드 데이터
            'daily_trends' => $this->getDailyTrends(),
            'hourly_trends' => $this->getHourlyTrends(),
        ];
    }

    /**
     * 고유 방문자 수 조회
     */
    public function getUniqueVisitors(Carbon $startDate, Carbon $endDate = null): int
    {
        $query = Analytics::where('created_at', '>=', $startDate);
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        return $query->distinct('ip_address')->count();
    }

    /**
     * 페이지뷰 수 조회
     */
    public function getPageViews(Carbon $startDate, Carbon $endDate = null): int
    {
        $query = Analytics::where('type', 'page_view')
            ->where('created_at', '>=', $startDate);
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        return $query->count();
    }

    /**
     * 인기 포스트 조회
     */
    public function getPopularPosts(int $limit = 10): Collection
    {
        return Analytics::where('type', 'page_view')
            ->whereNotNull('post_id')
            ->selectRaw('post_id, COUNT(*) as views')
            ->groupBy('post_id')
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->with('post:id,title,slug,published_at')
            ->get()
            ->map(function ($item) {
                return [
                    'post' => $item->post,
                    'views' => $item->views,
                    'title' => $item->post->title ?? '삭제된 포스트',
                    'url' => $item->post ? route('posts.show', $item->post->slug) : '#',
                ];
            });
    }

    /**
     * 인기 검색어 조회
     */
    public function getPopularSearches(int $limit = 10): Collection
    {
        return Analytics::where('type', 'search')
            ->whereNotNull('search_query')
            ->where('search_query', '!=', '')
            ->selectRaw('search_query, COUNT(*) as searches, AVG(search_results_count) as avg_results')
            ->groupBy('search_query')
            ->orderBy('searches', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'query' => $item->search_query,
                    'searches' => $item->searches,
                    'avg_results' => round($item->avg_results, 1),
                ];
            });
    }

    /**
     * 최근 검색어 조회
     */
    public function getRecentSearches(int $limit = 10): Collection
    {
        return Analytics::where('type', 'search')
            ->whereNotNull('search_query')
            ->where('search_query', '!=', '')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['search_query', 'search_results_count', 'created_at'])
            ->map(function ($item) {
                return [
                    'query' => $item->search_query,
                    'results' => $item->search_results_count,
                    'searched_at' => $item->created_at->diffForHumans(),
                ];
            });
    }

    /**
     * 브라우저 통계
     */
    public function getBrowserStats(int $limit = 10): Collection
    {
        return Analytics::selectRaw('browser, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_users')
            ->where('browser', '!=', 'Unknown')
            ->groupBy('browser')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'browser' => $item->browser,
                    'count' => $item->count,
                    'unique_users' => $item->unique_users,
                    'percentage' => 0, // 전체 대비 비율은 별도 계산
                ];
            });
    }

    /**
     * 디바이스 통계
     */
    public function getDeviceStats(): Collection
    {
        return Analytics::selectRaw('device_type, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_users')
            ->groupBy('device_type')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'device' => $item->device_type,
                    'count' => $item->count,
                    'unique_users' => $item->unique_users,
                ];
            });
    }

    /**
     * 플랫폼 통계
     */
    public function getPlatformStats(int $limit = 10): Collection
    {
        return Analytics::selectRaw('platform, COUNT(*) as count')
            ->where('platform', '!=', 'Unknown')
            ->groupBy('platform')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 일별 트렌드 (최근 30일)
     */
    public function getDailyTrends(int $days = 30): Collection
    {
        $startDate = Carbon::today()->subDays($days);
        
        return Analytics::selectRaw('DATE(created_at) as date, COUNT(*) as page_views, COUNT(DISTINCT ip_address) as unique_visitors')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('Y-m-d'),
                    'page_views' => $item->page_views,
                    'unique_visitors' => $item->unique_visitors,
                ];
            });
    }

    /**
     * 시간별 트렌드 (오늘)
     */
    public function getHourlyTrends(): Collection
    {
        $today = Carbon::today();
        
        return Analytics::selectRaw('HOUR(created_at) as hour, COUNT(*) as page_views, COUNT(DISTINCT ip_address) as unique_visitors')
            ->where('created_at', '>=', $today)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour . ':00',
                    'page_views' => $item->page_views,
                    'unique_visitors' => $item->unique_visitors,
                ];
            });
    }

    /**
     * 리퍼러 통계
     */
    public function getReferrerStats(int $limit = 10): Collection
    {
        return Analytics::selectRaw('referer, COUNT(*) as count')
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->where('referer', 'not like', '%' . request()->getHost() . '%') // 내부 링크 제외
            ->groupBy('referer')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $url = parse_url($item->referer);
                return [
                    'domain' => $url['host'] ?? $item->referer,
                    'full_url' => $item->referer,
                    'count' => $item->count,
                ];
            });
    }

    /**
     * 실시간 활동 (최근 30분)
     */
    public function getRealTimeActivity(): array
    {
        $thirtyMinutesAgo = Carbon::now()->subMinutes(30);
        
        $recentActivity = Analytics::where('created_at', '>=', $thirtyMinutesAgo)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return [
            'active_users' => $recentActivity->unique('ip_address')->count(),
            'recent_activity' => $recentActivity->map(function ($item) {
                return [
                    'type' => $item->type,
                    'page_title' => $item->page_title,
                    'page_url' => $item->page_url,
                    'time_ago' => $item->created_at->diffForHumans(),
                    'browser' => $item->browser,
                    'device' => $item->device_type,
                ];
            }),
        ];
    }
}