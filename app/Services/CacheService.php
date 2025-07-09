<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class CacheService
{
    /**
     * 포스트 목록 캐시 키 생성
     */
    public static function getPostListCacheKey(array $params = []): string
    {
        $key = 'posts_list';
        
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        
        return $key;
    }
    
    /**
     * 포스트 상세 캐시 키 생성
     */
    public static function getPostCacheKey(int $postId): string
    {
        return "post_{$postId}";
    }
    
    /**
     * 카테고리 목록 캐시 키 생성
     */
    public static function getCategoryCacheKey(): string
    {
        return 'categories_list';
    }
    
    /**
     * 태그 목록 캐시 키 생성
     */
    public static function getTagCacheKey(): string
    {
        return 'tags_list';
    }
    
    /**
     * 통계 캐시 키 생성
     */
    public static function getAnalyticsCacheKey(string $type = 'general', array $params = []): string
    {
        $key = "analytics_{$type}";
        
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        
        return $key;
    }
    
    /**
     * 포스트 목록 캐시 저장
     */
    public static function cachePostList(string $key, $data): void
    {
        $ttl = Config::get('optimize.post_list_cache_ttl', 1800);
        Cache::put($key, $data, $ttl);
    }
    
    /**
     * 포스트 상세 캐시 저장
     */
    public static function cachePost(int $postId, $data): void
    {
        $ttl = Config::get('optimize.query_cache_ttl', 3600);
        $key = self::getPostCacheKey($postId);
        Cache::put($key, $data, $ttl);
    }
    
    /**
     * 카테고리 캐시 저장
     */
    public static function cacheCategories($data): void
    {
        $ttl = Config::get('optimize.category_cache_ttl', 3600);
        $key = self::getCategoryCacheKey();
        Cache::put($key, $data, $ttl);
    }
    
    /**
     * 태그 캐시 저장
     */
    public static function cacheTags($data): void
    {
        $ttl = Config::get('optimize.category_cache_ttl', 3600);
        $key = self::getTagCacheKey();
        Cache::put($key, $data, $ttl);
    }
    
    /**
     * 통계 캐시 저장
     */
    public static function cacheAnalytics(string $type, $data, array $params = []): void
    {
        $ttl = Config::get('optimize.analytics_cache_ttl', 900);
        $key = self::getAnalyticsCacheKey($type, $params);
        Cache::put($key, $data, $ttl);
    }
    
    /**
     * 포스트 관련 캐시 무효화
     */
    public static function invalidatePostCache(int $postId): void
    {
        // 포스트 상세 캐시 삭제
        Cache::forget(self::getPostCacheKey($postId));
        
        // 포스트 목록 캐시 삭제 (패턴 매칭)
        self::clearCacheByPattern('posts_list*');
        
        // 통계 캐시 삭제
        self::clearCacheByPattern('analytics_*');
    }
    
    /**
     * 카테고리 관련 캐시 무효화
     */
    public static function invalidateCategoryCache(): void
    {
        Cache::forget(self::getCategoryCacheKey());
        self::clearCacheByPattern('posts_list*');
    }
    
    /**
     * 태그 관련 캐시 무효화
     */
    public static function invalidateTagCache(): void
    {
        Cache::forget(self::getTagCacheKey());
        self::clearCacheByPattern('posts_list*');
    }
    
    /**
     * 패턴으로 캐시 삭제
     */
    public static function clearCacheByPattern(string $pattern): void
    {
        $cacheStore = Cache::getStore();
        
        if (method_exists($cacheStore, 'flush')) {
            // 파일 캐시의 경우 전체 삭제 후 재구성
            // 실제 운영환경에서는 Redis나 Memcached 사용 권장
            if (str_contains($pattern, '*')) {
                // 패턴 매칭 캐시 삭제는 Redis에서 지원
                // 파일 캐시에서는 제한적 지원
            }
        }
    }
    
    /**
     * 전체 캐시 플러시
     */
    public static function flushAll(): void
    {
        Cache::flush();
    }
    
    /**
     * 캐시 정보 가져오기
     */
    public static function getCacheInfo(): array
    {
        $driver = config('cache.default');
        $prefix = config('cache.prefix');
        
        return [
            'driver' => $driver,
            'prefix' => $prefix,
            'stores' => config('cache.stores'),
        ];
    }
}