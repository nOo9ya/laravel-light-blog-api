<?php

namespace App\Http\Middleware;

use App\Models\Analytics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsMiddleware
{
    /**
     * 방문자 통계 수집 미들웨어
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // POST, PUT, DELETE 요청은 통계에서 제외
        if (!$request->isMethod('GET')) {
            return $response;
        }

        // 관리자 페이지, API, 개발 관련 경로 제외
        $excludedPaths = [
            'admin/*',
            'api/*',
            '_debugbar/*',
            'telescope/*',
            'horizon/*',
            'sitemap.xml',
            'robots.txt',
            'favicon.ico'
        ];

        foreach ($excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return $response;
            }
        }

        try {
            $this->recordAnalytics($request);
        } catch (\Exception $e) {
            // 통계 기록 실패해도 응답에 영향주지 않음
            \Log::warning('Analytics recording failed: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * 통계 데이터 기록
     * 
     * @param Request $request
     * @return void
     */
    private function recordAnalytics(Request $request): void
    {
        $userAgent = $request->userAgent() ?? '';
        $ipAddress = $request->ip();
        
        // 중복 방문 체크 (같은 IP, 같은 페이지, 10분 이내)
        $recentVisit = Analytics::where('ip_address', $ipAddress)
            ->where('page_url', $request->fullUrl())
            ->where('type', 'page_view')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($recentVisit) {
            return;
        }

        // User-Agent 파싱
        $browserInfo = $this->parseUserAgent($userAgent);
        
        // 포스트 ID 추출 (포스트 상세 페이지인 경우)
        $postId = null;
        if ($request->route() && $request->route()->getName() === 'posts.show') {
            $postId = $request->route('post')?->id;
        }

        // 검색 쿼리 추출
        $searchQuery = null;
        $searchResultsCount = null;
        $searchType = null;
        if ($request->route() && $request->route()->getName() === 'search.index') {
            $searchQuery = $request->get('q');
            $searchResultsCount = $request->get('results_count', 0);
            $searchType = $request->get('type', 'all');
        }

        Analytics::create([
            'post_id' => $postId,
            'user_id' => Auth::id(),
            'type' => $this->getEventType($request),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer' => $request->header('referer'),
            'session_id' => session()->getId(),
            'page_url' => $request->fullUrl(),
            'page_title' => $this->getPageTitle($request),
            'search_query' => $searchQuery,
            'search_results_count' => $searchResultsCount,
            'search_type' => $searchType,
            'browser' => $browserInfo['browser'],
            'browser_version' => $browserInfo['version'],
            'platform' => $browserInfo['platform'],
            'device_type' => $browserInfo['device_type'],
            'country' => null, // GeoIP 기능은 필요시 추가
            'city' => null,
        ]);
    }

    /**
     * 이벤트 타입 결정
     * 
     * @param Request $request
     * @return string
     */
    private function getEventType(Request $request): string
    {
        $routeName = $request->route()?->getName();

        return match ($routeName) {
            'search.index' => 'search',
            'posts.show' => 'page_view',
            'pages.show' => 'page_view',
            'categories.show' => 'page_view',
            'tags.show' => 'page_view',
            default => 'page_view'
        };
    }

    /**
     * 페이지 제목 추출
     * 
     * @param Request $request
     * @return string|null
     */
    private function getPageTitle(Request $request): ?string
    {
        $routeName = $request->route()?->getName();

        return match ($routeName) {
            'home' => '홈페이지',
            'posts.index' => '포스트 목록',
            'posts.show' => $request->route('post')?->title,
            'pages.show' => $request->route('page')?->title,
            'categories.show' => $request->route('category')?->name . ' 카테고리',
            'tags.show' => $request->route('tag')?->name . ' 태그',
            'search.index' => '검색 결과: ' . $request->get('q'),
            default => null
        };
    }

    /**
     * User-Agent 파싱
     * 
     * @param string $userAgent
     * @return array
     */
    private function parseUserAgent(string $userAgent): array
    {
        $browser = 'Unknown';
        $version = '';
        $platform = 'Unknown';
        $deviceType = 'Desktop';

        // 플랫폼 감지
        if (preg_match('/Windows/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $platform = 'Android';
            $deviceType = 'Mobile';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
            $platform = 'iOS';
            $deviceType = preg_match('/iPad/i', $userAgent) ? 'Tablet' : 'Mobile';
        }

        // 브라우저 감지
        if (preg_match('/Edge\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Edge';
            $version = $matches[1];
        } elseif (preg_match('/Chrome\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Chrome';
            $version = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/i', $userAgent, $matches) && !preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Safari';
            $version = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Firefox';
            $version = $matches[1];
        }

        // 모바일 디바이스 추가 감지
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent)) {
            if (preg_match('/iPad/i', $userAgent)) {
                $deviceType = 'Tablet';
            } elseif ($deviceType === 'Desktop') {
                $deviceType = 'Mobile';
            }
        }

        return [
            'browser' => $browser,
            'version' => $version,
            'platform' => $platform,
            'device_type' => $deviceType,
        ];
    }
}