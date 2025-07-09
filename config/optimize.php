<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 성능 최적화 설정
    |--------------------------------------------------------------------------
    */

    // 뷰 캐시 활성화
    'view_cache' => env('VIEW_CACHE_ENABLED', true),
    
    // 라우트 캐시 활성화  
    'route_cache' => env('ROUTE_CACHE_ENABLED', true),
    
    // 설정 캐시 활성화
    'config_cache' => env('CONFIG_CACHE_ENABLED', true),
    
    // 이벤트 캐시 활성화
    'event_cache' => env('EVENT_CACHE_ENABLED', true),
    
    // 쿼리 캐시 TTL (초)
    'query_cache_ttl' => env('QUERY_CACHE_TTL', 3600),
    
    // 이미지 캐시 TTL (초)
    'image_cache_ttl' => env('IMAGE_CACHE_TTL', 86400),
    
    // 포스트 목록 캐시 TTL (초)
    'post_list_cache_ttl' => env('POST_LIST_CACHE_TTL', 1800),
    
    // 카테고리/태그 캐시 TTL (초)
    'category_cache_ttl' => env('CATEGORY_CACHE_TTL', 3600),
    
    // 통계 캐시 TTL (초)
    'analytics_cache_ttl' => env('ANALYTICS_CACHE_TTL', 900),
    
    // Gzip 압축 활성화
    'gzip_enabled' => env('GZIP_ENABLED', true),
    
    // 브라우저 캐시 헤더 (초)
    'browser_cache_ttl' => env('BROWSER_CACHE_TTL', 2592000), // 30일
    
    // CDN 설정
    'cdn_enabled' => env('CDN_ENABLED', false),
    'cdn_url' => env('CDN_URL', ''),
    
    // 데이터베이스 최적화
    'db_strict_mode' => env('DB_STRICT_MODE', false),
    'db_query_log' => env('DB_QUERY_LOG', false),
    
    // 세션 최적화
    'session_gc_probability' => env('SESSION_GC_PROBABILITY', 1),
    'session_gc_divisor' => env('SESSION_GC_DIVISOR', 1000),
    
    // 메모리 제한
    'memory_limit' => env('MEMORY_LIMIT', '512M'),
    'max_execution_time' => env('MAX_EXECUTION_TIME', 60),
];