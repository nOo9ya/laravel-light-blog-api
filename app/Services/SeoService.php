<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Page;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class SeoService
{
    /**
     * 포스트용 SEO 메타데이터 생성
     */
    public static function getPostSeoData(Post $post): array
    {
        $seoMeta = $post->seoMeta;
        $siteName = config('app.name', 'Laravel Light Blog');
        $siteUrl = config('app.url');
        $defaultImage = $siteUrl . '/images/default-og.jpg';
        
        return [
            'title' => $seoMeta?->og_title ?: $post->title . ' - ' . $siteName,
            'description' => $seoMeta?->og_description ?: Str::limit(strip_tags($post->summary ?: $post->content), 160),
            'keywords' => $seoMeta?->meta_keywords ?: ($post->tags->pluck('name')->implode(', ')),
            'image' => $post->og_image ? ($siteUrl . '/storage/' . $post->og_image) : ($post->main_image ? ($siteUrl . '/storage/' . $post->main_image) : $defaultImage),
            'url' => route('posts.show', $post->slug),
            'type' => 'article',
            'author' => $post->user->name,
            'published_time' => $post->published_at?->toISOString(),
            'modified_time' => $post->updated_at->toISOString(),
            'section' => $post->category?->name,
            'tags' => $post->tags->pluck('name')->toArray(),
            'robots' => $seoMeta?->robots ?: 'index, follow',
        ];
    }
    
    /**
     * 페이지용 SEO 메타데이터 생성
     */
    public static function getPageSeoData(Page $page): array
    {
        $siteName = config('app.name', 'Laravel Light Blog');
        $siteUrl = config('app.url');
        $defaultImage = $siteUrl . '/images/default-og.jpg';
        
        return [
            'title' => $page->title . ' - ' . $siteName,
            'description' => Str::limit(strip_tags($page->excerpt ?: $page->content), 160),
            'keywords' => '',
            'image' => $defaultImage,
            'url' => route('pages.show', $page->slug),
            'type' => 'website',
            'robots' => 'index, follow',
        ];
    }
    
    /**
     * 기본 사이트 SEO 메타데이터 생성
     */
    public static function getDefaultSeoData(): array
    {
        $siteName = config('app.name', 'Laravel Light Blog');
        $siteUrl = config('app.url');
        $defaultImage = $siteUrl . '/images/default-og.jpg';
        
        return [
            'title' => $siteName,
            'description' => '경량화된 고성능 Laravel 블로그 시스템',
            'keywords' => 'Laravel, 블로그, PHP, 웹개발',
            'image' => $defaultImage,
            'url' => $siteUrl,
            'type' => 'website',
            'robots' => 'index, follow',
        ];
    }
    
    /**
     * JSON-LD 구조화 데이터 생성
     */
    public static function getJsonLd(Post $post): array
    {
        $siteUrl = config('app.url');
        $siteName = config('app.name', 'Laravel Light Blog');
        
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => Str::limit(strip_tags($post->summary ?: $post->content), 160),
            'author' => [
                '@type' => 'Person',
                'name' => $post->user->name,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $siteName,
                'url' => $siteUrl,
            ],
            'datePublished' => $post->published_at?->toISOString(),
            'dateModified' => $post->updated_at->toISOString(),
            'url' => route('posts.show', $post->slug),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => route('posts.show', $post->slug),
            ],
        ];
        
        // 이미지 추가
        if ($post->main_image || $post->og_image) {
            $imageUrl = $post->main_image ? 
                ($siteUrl . '/storage/' . $post->main_image) : 
                ($siteUrl . '/storage/' . $post->og_image);
                
            $jsonLd['image'] = [
                '@type' => 'ImageObject',
                'url' => $imageUrl,
            ];
        }
        
        // 카테고리 추가
        if ($post->category) {
            $jsonLd['articleSection'] = $post->category->name;
        }
        
        // 태그 추가
        if ($post->tags->count() > 0) {
            $jsonLd['keywords'] = $post->tags->pluck('name')->toArray();
        }
        
        return $jsonLd;
    }
    
    /**
     * 사이트맵용 URL 데이터 생성
     */
    public static function getSitemapUrls(): array
    {
        $urls = [];
        
        // 홈페이지
        $urls[] = [
            'url' => config('app.url'),
            'lastmod' => now()->toDateString(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];
        
        // 포스트 목록
        $urls[] = [
            'url' => route('posts.index'),
            'lastmod' => now()->toDateString(),
            'changefreq' => 'daily',
            'priority' => '0.9',
        ];
        
        // 발행된 포스트들
        Post::published()
            ->select(['slug', 'updated_at'])
            ->chunk(100, function ($posts) use (&$urls) {
                foreach ($posts as $post) {
                    $urls[] = [
                        'url' => route('posts.show', $post->slug),
                        'lastmod' => $post->updated_at->toDateString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ];
                }
            });
        
        // 페이지들
        Page::where('status', 'published')
            ->select(['slug', 'updated_at'])
            ->chunk(100, function ($pages) use (&$urls) {
                foreach ($pages as $page) {
                    $urls[] = [
                        'url' => route('pages.show', $page->slug),
                        'lastmod' => $page->updated_at->toDateString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.7',
                    ];
                }
            });
        
        return $urls;
    }
    
    /**
     * 메타태그 HTML 생성
     */
    public static function generateMetaTags(array $seoData): string
    {
        $html = '';
        
        // 기본 메타태그
        $html .= '<meta name="description" content="' . htmlspecialchars($seoData['description']) . '">' . "\n";
        $html .= '<meta name="keywords" content="' . htmlspecialchars($seoData['keywords']) . '">' . "\n";
        $html .= '<meta name="robots" content="' . htmlspecialchars($seoData['robots']) . '">' . "\n";
        
        // Open Graph 메타태그
        $html .= '<meta property="og:title" content="' . htmlspecialchars($seoData['title']) . '">' . "\n";
        $html .= '<meta property="og:description" content="' . htmlspecialchars($seoData['description']) . '">' . "\n";
        $html .= '<meta property="og:image" content="' . htmlspecialchars($seoData['image']) . '">' . "\n";
        $html .= '<meta property="og:url" content="' . htmlspecialchars($seoData['url']) . '">' . "\n";
        $html .= '<meta property="og:type" content="' . htmlspecialchars($seoData['type']) . '">' . "\n";
        $html .= '<meta property="og:site_name" content="' . htmlspecialchars(config('app.name')) . '">' . "\n";
        
        // Twitter Card 메타태그
        $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '<meta name="twitter:title" content="' . htmlspecialchars($seoData['title']) . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . htmlspecialchars($seoData['description']) . '">' . "\n";
        $html .= '<meta name="twitter:image" content="' . htmlspecialchars($seoData['image']) . '">' . "\n";
        
        // 포스트 관련 추가 메타태그
        if (isset($seoData['author'])) {
            $html .= '<meta name="author" content="' . htmlspecialchars($seoData['author']) . '">' . "\n";
        }
        
        if (isset($seoData['published_time'])) {
            $html .= '<meta property="article:published_time" content="' . htmlspecialchars($seoData['published_time']) . '">' . "\n";
        }
        
        if (isset($seoData['modified_time'])) {
            $html .= '<meta property="article:modified_time" content="' . htmlspecialchars($seoData['modified_time']) . '">' . "\n";
        }
        
        if (isset($seoData['section'])) {
            $html .= '<meta property="article:section" content="' . htmlspecialchars($seoData['section']) . '">' . "\n";
        }
        
        if (isset($seoData['tags']) && is_array($seoData['tags'])) {
            foreach ($seoData['tags'] as $tag) {
                $html .= '<meta property="article:tag" content="' . htmlspecialchars($tag) . '">' . "\n";
            }
        }
        
        return $html;
    }
}