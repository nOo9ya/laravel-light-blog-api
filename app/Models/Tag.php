<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
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
        'color',
        'post_count',
    ];

    protected $casts = [
        'post_count' => 'integer',
    ];

    protected $attributes = [
        'color' => '#3b82f6',
        'post_count' => 0,
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

        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = static::generateSlug($tag->name);
            }
        });

        static::updating(function (Tag $tag) {
            if ($tag->isDirty('name') && empty($tag->slug)) {
                $tag->slug = static::generateSlug($tag->name);
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
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withTimestamps();
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 스코프 (Scopes)
    |--------------------------------------------------------------------------
    */
    // region --- 스코프 ---
    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderBy('post_count', 'desc')->limit($limit);
    }

    public function scopeWithPosts($query)
    {
        return $query->where('post_count', '>', 0);
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 기타 메서드 (Additional Methods)
    |--------------------------------------------------------------------------
    */
    // region --- 기타 메서드 ---
    public function updatePostCount(): void
    {
        $this->update(['post_count' => $this->posts()->count()]);
    }

    public static function getTagCloud(int $limit = 50): \Illuminate\Support\Collection
    {
        return static::withPosts()
            ->orderBy('post_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function generateSlug(string $name): string
    {
        $slug = str_replace(' ', '-', $name);
        $slug = preg_replace('/[^가-힣a-zA-Z0-9\-_]/', '', $slug);
        return $slug ?: Str::random(10);
    }
    // endregion
}
