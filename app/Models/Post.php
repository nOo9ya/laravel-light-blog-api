<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Services\SlugService;

class Post extends Model
{
    use HasFactory;
    /*
    |--------------------------------------------------------------------------
    | 모델 속성 (Attributes & Properties)
    |--------------------------------------------------------------------------
    */
    // region --- 모델 속성 ---
    protected $fillable = [
        'title',
        'slug',
        'content',
        'summary',
        'timeline_json',
        'main_image',
        'og_image',
        'status',
        'published_at',
        'views_count',
        'user_id',
        'category_id',
    ];

    protected $casts = [
        'timeline_json' => 'array',
        'published_at' => 'datetime',
        'views_count' => 'integer',
    ];

    protected $attributes = [
        'status' => 'draft',
        'views_count' => 0,
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

        static::creating(function (Post $post) {
            if (empty($post->slug)) {
                $post->slug = static::generateSlug($post->title);
            }
        });

        static::updating(function (Post $post) {
            if ($post->isDirty('title') && empty($post->slug)) {
                $post->slug = static::generateSlug($post->title);
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function seoMeta(): HasOne
    {
        return $this->hasOne(SeoMeta::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PostAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->hasMany(Comment::class)->approved()->orderBy('created_at');
    }

    public function topLevelComments(): HasMany
    {
        return $this->hasMany(Comment::class)
            ->topLevel()
            ->approved()
            ->with(['user', 'replies'])
            ->orderBy('created_at');
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 스코프 (Scopes)
    |--------------------------------------------------------------------------
    */
    // region --- 스코프 ---
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    public function scopeWithCategory($query)
    {
        return $query->with('category');
    }

    public function scopeWithTags($query)
    {
        return $query->with('tags');
    }

    public function scopePopular($query)
    {
        return $query->orderBy('views_count', 'desc');
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 접근자/변경자 (Accessors & Mutators)
    |--------------------------------------------------------------------------
    */
    // region --- 접근자/변경자 ---
    public function getExcerptAttribute(): string
    {
        return $this->summary ?: Str::limit(strip_tags($this->content), 200);
    }

    public function getReadingTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        return ceil($wordCount / 200);
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === 'published' && 
               $this->published_at && 
               $this->published_at->isPast();
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 기타 메서드 (Additional Methods)
    |--------------------------------------------------------------------------
    */
    // region --- 기타 메서드 ---
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getUrl(): string
    {
        return route('posts.show', $this->slug);
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    protected static function generateSlug(string $title): string
    {
        $baseSlug = SlugService::generateAutoSlug($title);
        return SlugService::makeUniqueSlug($baseSlug, self::class);
    }
    // endregion
}
