<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analytics;
use App\Models\Post;
use App\Models\User;
use App\Models\Category;
use App\Models\Tag;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Analytics",
 *     description="분석 및 통계 API (관리자 전용)"
 * )
 */
class AnalyticsController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/admin/analytics/dashboard",
     *     summary="대시보드 통계",
     *     description="전체적인 대시보드 통계를 조회합니다 (관리자만 가능)",
     *     tags={"Analytics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="대시보드 통계 조회 성공",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="대시보드 통계를 조회했습니다"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="overview",
     *                     type="object",
     *                     @OA\Property(property="total_posts", type="integer", example=150),
     *                     @OA\Property(property="total_users", type="integer", example=25),
     *                     @OA\Property(property="total_comments", type="integer", example=300),
     *                     @OA\Property(property="total_views", type="integer", example=5000)
     *                 ),
     *                 @OA\Property(
     *                     property="recent_activity",
     *                     type="object",
     *                     @OA\Property(property="new_posts_today", type="integer", example=3),
     *                     @OA\Property(property="new_users_today", type="integer", example=2),
     *                     @OA\Property(property="new_comments_today", type="integer", example=8)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function dashboard(): JsonResponse
    {
        try {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();

            $overview = [
                'total_posts' => Post::count(),
                'published_posts' => Post::where('status', 'published')->count(),
                'draft_posts' => Post::where('status', 'draft')->count(),
                'total_users' => User::count(),
                'total_categories' => Category::count(),
                'total_tags' => Tag::count(),
            ];

            // Analytics 테이블이 있다면 조회, 없다면 0으로 설정
            try {
                $overview['total_views'] = Analytics::where('event_type', 'page_view')->count();
                $overview['total_searches'] = Analytics::where('event_type', 'search')->count();
            } catch (\Exception $e) {
                $overview['total_views'] = 0;
                $overview['total_searches'] = 0;
            }

            $recentActivity = [
                'new_posts_today' => Post::whereDate('created_at', $today)->count(),
                'new_users_today' => User::whereDate('created_at', $today)->count(),
                'new_posts_this_month' => Post::where('created_at', '>=', $thisMonth)->count(),
                'new_users_this_month' => User::where('created_at', '>=', $thisMonth)->count(),
            ];

            // 최근 7일간 포스트 작성 통계
            $weeklyPosts = Post::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date')
                ->map(function ($item) {
                    return $item->count;
                });

            // 지난 7일 채우기
            $weeklyData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');
                $weeklyData[$date] = $weeklyPosts->get($date, 0);
            }

            $data = [
                'overview' => $overview,
                'recent_activity' => $recentActivity,
                'weekly_posts' => $weeklyData,
                'last_updated' => now(),
            ];

            return $this->successResponse($data, '대시보드 통계를 조회했습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'ANALYTICS_ERROR',
                '통계 조회 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/analytics/posts",
     *     summary="포스트 분석",
     *     description="포스트 관련 통계를 조회합니다 (관리자만 가능)",
     *     tags={"Analytics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="조회 기간 (7days, 30days, 90days, 1year)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"7days", "30days", "90days", "1year"}, default="30days")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="포스트 통계 조회 성공"
     *     )
     * )
     */
    public function posts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|string|in:7days,30days,90days,1year',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $period = $request->get('period', '30days');
        $days = match($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            '1year' => 365,
            default => 30
        };

        try {
            $startDate = Carbon::now()->subDays($days);

            // 기간별 포스트 통계
            $postStats = [
                'total_posts' => Post::count(),
                'published_posts' => Post::where('status', 'published')->count(),
                'draft_posts' => Post::where('status', 'draft')->count(),
                'posts_in_period' => Post::where('created_at', '>=', $startDate)->count(),
            ];

            // 카테고리별 포스트 수
            $postsByCategory = Post::select('categories.name', DB::raw('COUNT(posts.id) as count'))
                ->join('categories', 'posts.category_id', '=', 'categories.id')
                ->where('posts.status', 'published')
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // 작성자별 포스트 수
            $postsByAuthor = Post::select('users.name', DB::raw('COUNT(posts.id) as count'))
                ->join('users', 'posts.user_id', '=', 'users.id')
                ->where('posts.status', 'published')
                ->groupBy('users.id', 'users.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // 월별 포스트 발행 추이
            $monthlyPosts = Post::select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('status', 'published')
                ->where('created_at', '>=', $startDate)
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'period' => sprintf('%04d-%02d', $item->year, $item->month),
                        'count' => $item->count
                    ];
                });

            $data = [
                'stats' => $postStats,
                'by_category' => $postsByCategory,
                'by_author' => $postsByAuthor,
                'monthly_trend' => $monthlyPosts,
                'period' => $period,
            ];

            return $this->successResponse($data, '포스트 통계를 조회했습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'ANALYTICS_ERROR',
                '포스트 통계 조회 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/analytics/users",
     *     summary="사용자 분석",
     *     description="사용자 관련 통계를 조회합니다 (관리자만 가능)",
     *     tags={"Analytics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="사용자 통계 조회 성공"
     *     )
     * )
     */
    public function users(): JsonResponse
    {
        try {
            $userStats = [
                'total_users' => User::count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'unverified_users' => User::whereNull('email_verified_at')->count(),
                'by_role' => [
                    'admin' => User::where('role', 'admin')->count(),
                    'author' => User::where('role', 'author')->count(),
                    'user' => User::where('role', 'user')->count(),
                ],
                'recent_registrations' => [
                    'today' => User::whereDate('created_at', Carbon::today())->count(),
                    'this_week' => User::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
                    'this_month' => User::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
                ]
            ];

            // 월별 가입자 추이 (최근 12개월)
            $monthlyRegistrations = User::select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('created_at', '>=', Carbon::now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'period' => sprintf('%04d-%02d', $item->year, $item->month),
                        'count' => $item->count
                    ];
                });

            // 활성 사용자 통계
            $activeUsers = [
                'with_posts' => User::has('posts')->count(),
                'with_published_posts' => User::whereHas('posts', function ($query) {
                    $query->where('status', 'published');
                })->count(),
            ];

            $data = [
                'stats' => $userStats,
                'monthly_registrations' => $monthlyRegistrations,
                'active_users' => $activeUsers,
            ];

            return $this->successResponse($data, '사용자 통계를 조회했습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'ANALYTICS_ERROR',
                '사용자 통계 조회 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/analytics/traffic",
     *     summary="트래픽 분석",
     *     description="사이트 트래픽 통계를 조회합니다 (관리자만 가능)",
     *     tags={"Analytics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="조회 기간 (7days, 30days, 90days)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"7days", "30days", "90days"}, default="30days")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="트래픽 통계 조회 성공"
     *     )
     * )
     */
    public function traffic(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|string|in:7days,30days,90days',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $period = $request->get('period', '30days');
        $days = match($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30
        };

        try {
            $startDate = Carbon::now()->subDays($days);

            // Analytics 테이블이 없을 경우를 대비한 기본값
            $trafficStats = [
                'total_page_views' => 0,
                'unique_visitors' => 0,
                'total_searches' => 0,
                'avg_daily_views' => 0,
            ];

            try {
                // 페이지 뷰 통계
                $pageViews = Analytics::where('event_type', 'page_view')
                    ->where('created_at', '>=', $startDate)
                    ->count();

                $searches = Analytics::where('event_type', 'search')
                    ->where('created_at', '>=', $startDate)
                    ->count();

                $uniqueVisitors = Analytics::where('created_at', '>=', $startDate)
                    ->distinct('ip_address')
                    ->count('ip_address');

                $trafficStats = [
                    'total_page_views' => $pageViews,
                    'unique_visitors' => $uniqueVisitors,
                    'total_searches' => $searches,
                    'avg_daily_views' => round($pageViews / $days, 2),
                ];

                // 일별 트래픽 추이
                $dailyTraffic = Analytics::select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('COUNT(*) as views'),
                        DB::raw('COUNT(DISTINCT ip_address) as unique_visitors')
                    )
                    ->where('event_type', 'page_view')
                    ->where('created_at', '>=', $startDate)
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->keyBy('date');

                // 지난 기간 채우기
                $dailyData = [];
                for ($i = $days - 1; $i >= 0; $i--) {
                    $date = Carbon::now()->subDays($i)->format('Y-m-d');
                    $data = $dailyTraffic->get($date);
                    $dailyData[] = [
                        'date' => $date,
                        'views' => $data ? $data->views : 0,
                        'unique_visitors' => $data ? $data->unique_visitors : 0,
                    ];
                }

                // 인기 페이지
                $popularPages = Analytics::select('url', DB::raw('COUNT(*) as views'))
                    ->where('event_type', 'page_view')
                    ->where('created_at', '>=', $startDate)
                    ->groupBy('url')
                    ->orderBy('views', 'desc')
                    ->limit(10)
                    ->get();

            } catch (\Exception $e) {
                // Analytics 테이블이 없는 경우
                $dailyData = [];
                for ($i = $days - 1; $i >= 0; $i--) {
                    $date = Carbon::now()->subDays($i)->format('Y-m-d');
                    $dailyData[] = [
                        'date' => $date,
                        'views' => 0,
                        'unique_visitors' => 0,
                    ];
                }
                $popularPages = collect([]);
            }

            $data = [
                'stats' => $trafficStats,
                'daily_traffic' => $dailyData,
                'popular_pages' => $popularPages,
                'period' => $period,
            ];

            return $this->successResponse($data, '트래픽 통계를 조회했습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'ANALYTICS_ERROR',
                '트래픽 통계 조회 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/analytics/popular-content",
     *     summary="인기 콘텐츠 분석",
     *     description="인기 포스트 및 검색어 통계를 조회합니다 (관리자만 가능)",
     *     tags={"Analytics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="인기 콘텐츠 통계 조회 성공"
     *     )
     * )
     */
    public function popularContent(): JsonResponse
    {
        try {
            // 가장 많이 조회된 포스트 (views 컬럼이 있다면)
            $popularPosts = Post::select(['id', 'title', 'slug', 'views', 'created_at'])
                ->where('status', 'published')
                ->orderBy('views', 'desc')
                ->limit(10)
                ->get();

            // 최근 인기 포스트 (최근 30일간)
            $recentPopularPosts = Post::select(['id', 'title', 'slug', 'views', 'created_at'])
                ->where('status', 'published')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->orderBy('views', 'desc')
                ->limit(10)
                ->get();

            // 가장 많은 댓글을 받은 포스트
            $mostCommentedPosts = Post::select(['id', 'title', 'slug', 'created_at'])
                ->withCount(['comments' => function ($query) {
                    $query->where('status', 'approved');
                }])
                ->where('status', 'published')
                ->orderBy('comments_count', 'desc')
                ->limit(10)
                ->get();

            // 인기 검색어 (Analytics 테이블이 있다면)
            $popularSearches = [];
            try {
                $popularSearches = Analytics::where('event_type', 'search')
                    ->where('created_at', '>=', Carbon::now()->subDays(30))
                    ->selectRaw('event_data, COUNT(*) as search_count')
                    ->groupBy('event_data')
                    ->orderBy('search_count', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {
                        $data = json_decode($item->event_data, true);
                        return [
                            'query' => $data['query'] ?? '',
                            'count' => $item->search_count
                        ];
                    })
                    ->filter(function ($item) {
                        return !empty($item['query']);
                    })
                    ->values();
            } catch (\Exception $e) {
                // Analytics 테이블이 없는 경우
            }

            // 인기 카테고리 (포스트 수 기준)
            $popularCategories = Category::withCount(['posts' => function ($query) {
                    $query->where('status', 'published');
                }])
                ->orderBy('posts_count', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'slug', 'posts_count']);

            // 인기 태그 (포스트 수 기준)
            $popularTags = Tag::withCount(['posts' => function ($query) {
                    $query->where('status', 'published');
                }])
                ->orderBy('posts_count', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'slug', 'posts_count']);

            $data = [
                'popular_posts' => $popularPosts,
                'recent_popular_posts' => $recentPopularPosts,
                'most_commented_posts' => $mostCommentedPosts,
                'popular_searches' => $popularSearches,
                'popular_categories' => $popularCategories,
                'popular_tags' => $popularTags,
            ];

            return $this->successResponse($data, '인기 콘텐츠 통계를 조회했습니다');

        } catch (\Exception $e) {
            return $this->serverErrorResponse(
                'ANALYTICS_ERROR',
                '인기 콘텐츠 통계 조회 중 오류가 발생했습니다: ' . $e->getMessage()
            );
        }
    }
}