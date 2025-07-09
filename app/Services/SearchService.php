<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Page;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Analytics;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SearchService
{
    /**
     * 통합 검색 수행
     */
    public function search(string $query, string $type = 'all', int $limit = 10): array
    {
        // 검색어 전처리 및 보안 강화
        $searchTerm = $this->sanitizeSearchQuery($query);
        if (!$searchTerm) {
            return ['posts' => collect(), 'pages' => collect(), 'categories' => collect(), 'tags' => collect(), 'total' => 0, 'query' => $query];
        }

        $results = [];
        $totalResults = 0;

        if ($type === 'all' || $type === 'posts') {
            $results['posts'] = $this->searchPosts($searchTerm, $limit);
            $totalResults += $results['posts']->count();
        } else {
            $results['posts'] = collect();
        }

        if ($type === 'all' || $type === 'pages') {
            $results['pages'] = $this->searchPages($searchTerm, $limit);
            $totalResults += $results['pages']->count();
        } else {
            $results['pages'] = collect();
        }

        if ($type === 'all' || $type === 'categories') {
            $results['categories'] = $this->searchCategories($searchTerm, $limit);
            $totalResults += $results['categories']->count();
        } else {
            $results['categories'] = collect();
        }

        if ($type === 'all' || $type === 'tags') {
            $results['tags'] = $this->searchTags($searchTerm, $limit);
            $totalResults += $results['tags']->count();
        } else {
            $results['tags'] = collect();
        }

        $results['total'] = $totalResults;
        $results['query'] = $query;

        return $results;
    }

    /**
     * 페이지네이션을 지원하는 포스트 검색
     */
    public function searchPostsWithPagination(string $query, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $searchTerm = $this->sanitizeSearchQuery($query);
        
        $posts = Post::published()
            ->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('summary', 'LIKE', "%{$searchTerm}%");
            })
            ->with(['user:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->orderByRaw("
                CASE 
                    WHEN title LIKE '{$searchTerm}%' THEN 1
                    WHEN title LIKE '%{$searchTerm}%' THEN 2
                    WHEN summary LIKE '%{$searchTerm}%' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('published_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return $posts;
    }

    /**
     * 포스트 검색
     */
    protected function searchPosts(string $searchTerm, int $limit): Collection
    {
        return Post::published()
            ->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('summary', 'LIKE', "%{$searchTerm}%");
            })
            ->with(['user:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->orderByRaw("
                CASE 
                    WHEN title LIKE '{$searchTerm}%' THEN 1
                    WHEN title LIKE '%{$searchTerm}%' THEN 2
                    WHEN summary LIKE '%{$searchTerm}%' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 페이지 검색
     */
    protected function searchPages(string $searchTerm, int $limit): Collection
    {
        return Page::where('status', 'published')
            ->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('excerpt', 'LIKE', "%{$searchTerm}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN title LIKE '{$searchTerm}%' THEN 1
                    WHEN title LIKE '%{$searchTerm}%' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 카테고리 검색
     */
    protected function searchCategories(string $searchTerm, int $limit): Collection
    {
        return Category::where('name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('description', 'LIKE', "%{$searchTerm}%")
            ->withCount('posts')
            ->orderByRaw("
                CASE 
                    WHEN name LIKE '{$searchTerm}%' THEN 1
                    WHEN name LIKE '%{$searchTerm}%' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('posts_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 태그 검색
     */
    protected function searchTags(string $searchTerm, int $limit): Collection
    {
        return Tag::where('name', 'LIKE', "%{$searchTerm}%")
            ->withCount('posts')
            ->orderByRaw("
                CASE 
                    WHEN name LIKE '{$searchTerm}%' THEN 1
                    WHEN name LIKE '%{$searchTerm}%' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('posts_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 검색어 전처리 및 보안 강화
     */
    protected function sanitizeSearchQuery(string $query): ?string
    {
        // 특수문자 제거 및 공백 정리
        $sanitized = trim(preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $query));
        
        // 최소 길이 검증
        if (mb_strlen($sanitized) < 2) {
            return null;
        }
        
        // SQL 인젝션 방지를 위한 추가 검증
        $dangerous = ['select', 'union', 'insert', 'update', 'delete', 'drop', 'create', 'alter'];
        $lowerQuery = strtolower($sanitized);
        
        foreach ($dangerous as $keyword) {
            if (strpos($lowerQuery, $keyword) !== false) {
                return null;
            }
        }
        
        return $sanitized;
    }

    /**
     * 검색 로그 기록
     */
    public function logSearch(string $query, string $type, int $totalResults, string $ip): void
    {
        try {
            Analytics::create([
                'event_type' => 'search',
                'event_data' => [
                    'query' => $query,
                    'type' => $type,
                    'results_count' => $totalResults,
                    'timestamp' => now()->toISOString(),
                ],
                'user_id' => auth()->id(),
                'ip_address' => $ip,
                'user_agent' => request()->userAgent(),
                'url' => request()->fullUrl(),
                'referrer' => request()->header('referer'),
            ]);
        } catch (\Exception $e) {
            Log::error('검색 로그 기록 실패: ' . $e->getMessage());
        }
    }

    /**
     * 인기 검색어 조회
     */
    public function getPopularSearches(int $limit = 10, int $days = 30): array
    {
        $cacheKey = "popular_searches_{$limit}_{$days}";
        
        return Cache::remember($cacheKey, 3600, function () use ($limit, $days) {
            $searches = Analytics::where('event_type', 'search')
                ->where('created_at', '>', now()->subDays($days))
                ->get()
                ->pluck('event_data')
                ->filter(function ($data) {
                    return isset($data['query']) && mb_strlen($data['query']) >= 2;
                })
                ->groupBy('query')
                ->map(function ($group) {
                    return [
                        'query' => $group->first()['query'],
                        'count' => $group->count(),
                        'avg_results' => round($group->avg('results_count'), 1),
                    ];
                })
                ->sortByDesc('count')
                ->take($limit)
                ->values()
                ->toArray();
                
            return $searches;
        });
    }

    /**
     * 자동완성 검색어 제안
     */
    public function getAutocompleteSuggestions(string $query, int $limit = 5): array
    {
        $searchTerm = $this->sanitizeSearchQuery($query);
        if (!$searchTerm) {
            return [];
        }

        $suggestions = [];

        // 포스트 제목에서 제안
        $postTitles = Post::published()
            ->where('title', 'LIKE', "{$searchTerm}%")
            ->select('title')
            ->limit($limit)
            ->pluck('title')
            ->toArray();

        // 태그에서 제안
        $tagNames = Tag::where('name', 'LIKE', "{$searchTerm}%")
            ->select('name')
            ->limit($limit)
            ->pluck('name')
            ->toArray();

        // 카테고리에서 제안
        $categoryNames = Category::where('name', 'LIKE', "{$searchTerm}%")
            ->select('name')
            ->limit($limit)
            ->pluck('name')
            ->toArray();

        // 인기 검색어에서 제안
        $popularSearches = $this->getPopularSearches(20);
        $popularMatches = array_filter($popularSearches, function ($search) use ($searchTerm) {
            return stripos($search['query'], $searchTerm) === 0;
        });

        // 모든 제안 합치기
        $allSuggestions = array_unique(array_merge(
            $postTitles,
            $tagNames, 
            $categoryNames,
            array_column(array_slice($popularMatches, 0, $limit), 'query')
        ));

        // 관련성 순으로 정렬 (정확히 일치하는 것부터)
        usort($allSuggestions, function ($a, $b) use ($searchTerm) {
            $aStartsWith = stripos($a, $searchTerm) === 0;
            $bStartsWith = stripos($b, $searchTerm) === 0;
            
            if ($aStartsWith && !$bStartsWith) return -1;
            if (!$aStartsWith && $bStartsWith) return 1;
            
            return strlen($a) - strlen($b);
        });

        return array_slice($allSuggestions, 0, $limit);
    }

    /**
     * 관련 검색어 제안
     */
    public function getRelatedSearches(string $query, int $limit = 5): array
    {
        $searchTerm = $this->sanitizeSearchQuery($query);
        if (!$searchTerm) {
            return [];
        }

        // 검색어와 함께 검색된 다른 키워드들 찾기
        $relatedSearches = Analytics::where('event_type', 'search')
            ->where('created_at', '>', now()->subDays(30))
            ->whereJsonContains('event_data->query', $searchTerm)
            ->get()
            ->pluck('event_data.query')
            ->filter(function ($q) use ($searchTerm) {
                return $q !== $searchTerm && mb_strlen($q) >= 2;
            })
            ->countBy()
            ->sortDesc()
            ->take($limit)
            ->keys()
            ->toArray();

        return $relatedSearches;
    }

    /**
     * 검색 통계 조회
     */
    public function getSearchStats(int $days = 30): array
    {
        $searches = Analytics::where('event_type', 'search')
            ->where('created_at', '>', now()->subDays($days))
            ->get();

        $totalSearches = $searches->count();
        $uniqueQueries = $searches->pluck('event_data.query')->unique()->count();
        $avgResultsPerSearch = $searches->avg(function ($search) {
            return $search->event_data['results_count'] ?? 0;
        });

        return [
            'total_searches' => $totalSearches,
            'unique_queries' => $uniqueQueries,
            'avg_results_per_search' => round($avgResultsPerSearch, 1),
            'searches_per_day' => round($totalSearches / $days, 1),
            'popular_searches' => $this->getPopularSearches(10, $days),
        ];
    }
}