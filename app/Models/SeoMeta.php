<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoMeta extends Model
{
    /*
    |--------------------------------------------------------------------------
    | 모델 속성 (Attributes & Properties)
    |--------------------------------------------------------------------------
    */
    // region --- 모델 속성 ---
    protected $fillable = [
        'post_id',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'canonical_url',
        'meta_keywords',
        'robots',
        'custom_meta',
    ];

    protected $casts = [
        'custom_meta' => 'array',
    ];

    protected $attributes = [
        'og_type' => 'article',
        'twitter_card' => 'summary_large_image',
        'robots' => 'index,follow',
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
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 접근자/변경자 (Accessors & Mutators)
    |--------------------------------------------------------------------------
    */
    // region --- 접근자/변경자 ---
    public function getOgTitleAttribute($value): string
    {
        return $value ?: $this->post?->title ?: '';
    }

    public function getOgDescriptionAttribute($value): string
    {
        if ($value) {
            return $value;
        }
        
        if ($this->post?->summary) {
            return $this->post->summary;
        }
        
        return $this->post ? \Illuminate\Support\Str::limit(strip_tags($this->post->content), 160) : '';
    }

    public function getTwitterTitleAttribute($value): string
    {
        return $value ?: $this->og_title;
    }

    public function getTwitterDescriptionAttribute($value): string
    {
        return $value ?: $this->og_description;
    }

    public function getTwitterImageAttribute($value): string
    {
        return $value ?: $this->og_image ?: '';
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 기타 메서드 (Additional Methods)
    |--------------------------------------------------------------------------
    */
    // region --- 기타 메서드 ---
    public function generateCanonicalUrl(): string
    {
        if ($this->canonical_url) {
            return $this->canonical_url;
        }
        
        return $this->post ? $this->post->getUrl() : '';
    }

    public function getMetaKeywordsArray(): array
    {
        if (!$this->meta_keywords) {
            return [];
        }
        
        return array_map('trim', explode(',', $this->meta_keywords));
    }

    public function setMetaKeywordsFromArray(array $keywords): void
    {
        $this->meta_keywords = implode(', ', $keywords);
    }
    // endregion
}
