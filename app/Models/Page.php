<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Page extends Model
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
        'excerpt',
        'meta_title',
        'meta_description',
        'is_published',
        'show_in_menu',
        'order',
        'user_id',
        'category_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'show_in_menu' => 'boolean',
    ];

    protected $attributes = [
        'is_published' => true,
        'show_in_menu' => false,
        'order' => 0,
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

        static::creating(function (Page $page) {
            if (empty($page->slug)) {
                $page->slug = static::generateSlug($page->title);
            }
        });

        static::updating(function (Page $page) {
            if ($page->isDirty('title') && empty($page->slug)) {
                $page->slug = static::generateSlug($page->title);
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
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 스코프 (Scopes)
    |--------------------------------------------------------------------------
    */
    // region --- 스코프 ---
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeInMenu($query)
    {
        return $query->where('show_in_menu', true)->orderBy('order');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 접근자/변경자 (Accessors & Mutators)
    |--------------------------------------------------------------------------
    */
    // region --- 접근자/변경자 ---
    public function getMetaTitleAttribute($value): string
    {
        return $value ?: $this->title;
    }

    public function getMetaDescriptionAttribute($value): string
    {
        if ($value) {
            return $value;
        }
        
        if ($this->excerpt) {
            return $this->excerpt;
        }
        
        return Str::limit(strip_tags($this->content), 160);
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
        return route('pages.show', $this->slug);
    }

    protected static function generateSlug(string $name): string
    {
        $slug = str_replace(' ', '-', $name);
        $slug = preg_replace('/[^가-힣a-zA-Z0-9\-_]/', '', $slug);
        return $slug ?: Str::random(10);
    }
    // endregion
}
