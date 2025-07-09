<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;
    /*
    |--------------------------------------------------------------------------
    | 모델 속성 (Attributes & Properties)
    |--------------------------------------------------------------------------
    */
    // region --- 모델 속성 ---
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'parent_id',
        'sort_order',
        'is_active',
        'icon',
        'color',
        'image',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'type' => 'both',
        'is_active' => true,
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

        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = static::generateSlug($category->name);
            }
        });

        static::updating(function (Category $category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = static::generateSlug($category->name);
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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 스코프 (Scopes)
    |--------------------------------------------------------------------------
    */
    // region --- 스코프 ---
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeParent($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeForPosts($query)
    {
        return $query->whereIn('type', ['post', 'both']);
    }

    public function scopeForPages($query)
    {
        return $query->whereIn('type', ['page', 'both']);
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 접근자/변경자 (Accessors & Mutators)
    |--------------------------------------------------------------------------
    */
    // region --- 접근자/변경자 ---
    public function getFullNameAttribute(): string
    {
        $names = collect([$this->name]);
        $parent = $this->parent;
        
        while ($parent) {
            $names->prepend($parent->name);
            $parent = $parent->parent;
        }
        
        return $names->implode(' > ');
    }

    public function getDepthAttribute(): int
    {
        $depth = 0;
        $parent = $this->parent;
        
        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }
        
        return $depth;
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 기타 메서드 (Additional Methods)
    |--------------------------------------------------------------------------
    */
    // region --- 기타 메서드 ---
    public function getAllChildren(): \Illuminate\Support\Collection
    {
        $children = collect();
        
        foreach ($this->children as $child) {
            $children->push($child);
            $children = $children->merge($child->getAllChildren());
        }
        
        return $children;
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function isChildOf(Category $category): bool
    {
        $parent = $this->parent;
        
        while ($parent) {
            if ($parent->id === $category->id) {
                return true;
            }
            $parent = $parent->parent;
        }
        
        return false;
    }

    protected static function generateSlug(string $name): string
    {
        $slug = str_replace(' ', '-', $name);
        $slug = preg_replace('/[^가-힣a-zA-Z0-9\-_]/', '', $slug);
        return $slug ?: Str::random(10);
    }
    // endregion
}
