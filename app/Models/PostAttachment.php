<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PostAttachment extends Model
{
    /*
    |--------------------------------------------------------------------------
    | 모델 속성 (Attributes & Properties)
    |--------------------------------------------------------------------------
    */
    // region --- 모델 속성 ---
    protected $fillable = [
        'post_id',
        'filename',
        'original_name',
        'path',
        'mime_type',
        'size',
        'type',
        'description',
        'download_count',
        'sort_order',
        'is_public',
    ];

    protected $casts = [
        'size' => 'integer',
        'download_count' => 'integer',
        'sort_order' => 'integer',
        'is_public' => 'boolean',
    ];

    protected $attributes = [
        'download_count' => 0,
        'sort_order' => 0,
        'is_public' => true,
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
    | 스코프 (Scopes)
    |--------------------------------------------------------------------------
    */
    // region --- 스코프 ---
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    public function scopeDocuments($query)
    {
        return $query->where('type', 'document');
    }

    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 접근자/변경자 (Accessors & Mutators)
    |--------------------------------------------------------------------------
    */
    // region --- 접근자/변경자 ---
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    public function getFullUrlAttribute(): string
    {
        return asset($this->url);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getIconAttribute(): string
    {
        return match($this->type) {
            'image' => 'fas fa-image',
            'video' => 'fas fa-video',
            'audio' => 'fas fa-music',
            'document' => 'fas fa-file-alt',
            'archive' => 'fas fa-file-archive',
            'code' => 'fas fa-code',
            default => 'fas fa-file',
        };
    }

    public function getIsImageAttribute(): bool
    {
        return $this->type === 'image';
    }

    public function getIsDocumentAttribute(): bool
    {
        return $this->type === 'document';
    }
    // endregion

    /*
    |--------------------------------------------------------------------------
    | 기타 메서드 (Additional Methods)
    |--------------------------------------------------------------------------
    */
    // region --- 기타 메서드 ---
    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    public function delete(): ?bool
    {
        if (Storage::exists($this->path)) {
            Storage::delete($this->path);
        }
        
        return parent::delete();
    }

    public function getFileTypeFromMimeType(): string
    {
        $mimeType = $this->mime_type;
        
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ])) {
            return 'document';
        } elseif (in_array($mimeType, [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
        ])) {
            return 'archive';
        } elseif (in_array($mimeType, [
            'text/plain',
            'text/html',
            'text/css',
            'text/javascript',
            'application/javascript',
            'application/json',
            'application/xml',
        ])) {
            return 'code';
        } else {
            return 'file';
        }
    }

    public static function determineTypeFromMimeType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ];
        
        if (in_array($mimeType, $documentTypes)) {
            return 'document';
        }
        
        return 'other';
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($attachment) {
            if (empty($attachment->type)) {
                $attachment->type = $attachment->getFileTypeFromMimeType();
            }
        });
    }
    // endregion
}
