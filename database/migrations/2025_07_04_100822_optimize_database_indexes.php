<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Posts 테이블 인덱스 최적화
        try {
            Schema::table('posts', function (Blueprint $table) {
                $table->index(['category_id', 'status', 'published_at']);
                $table->index(['user_id', 'status', 'created_at']);
                $table->index(['views_count']);
                $table->index(['title']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Categories 테이블 인덱스 최적화
        try {
            Schema::table('categories', function (Blueprint $table) {
                $table->index(['parent_id', 'type', 'is_active']);
                $table->index(['type', 'is_active', 'sort_order']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Post_tag 피벗 테이블 인덱스 최적화
        try {
            Schema::table('post_tag', function (Blueprint $table) {
                $table->index(['tag_id', 'post_id']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Comments 테이블 인덱스 최적화
        try {
            Schema::table('comments', function (Blueprint $table) {
                $table->index(['post_id', 'status', 'created_at']);
                $table->index(['status', 'created_at']);
                $table->index(['author_email']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Pages 테이블 인덱스 최적화
        try {
            Schema::table('pages', function (Blueprint $table) {
                $table->index(['is_published', 'created_at']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Seo_metas 테이블 인덱스 최적화
        try {
            Schema::table('seo_metas', function (Blueprint $table) {
                $table->index(['seoable_type', 'seoable_id']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Users 테이블 인덱스 최적화
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['role']);
                $table->index(['created_at']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Cache 테이블 생성 (파일 캐시가 아닌 DB 캐시 사용시)
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
                
                $table->index(['expiration']);
            });
        }

        // Cache locks 테이블 생성
        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
                
                $table->index(['expiration']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 인덱스 제거 (존재하지 않을 수 있으므로 try-catch 사용)
        try {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropIndex(['category_id', 'status', 'published_at']);
                $table->dropIndex(['user_id', 'status', 'created_at']);
                $table->dropIndex(['views_count']);
                $table->dropIndex(['title']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropIndex(['parent_id', 'type', 'is_active']);
                $table->dropIndex(['type', 'is_active', 'sort_order']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('post_tag', function (Blueprint $table) {
                $table->dropIndex(['tag_id', 'post_id']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('comments', function (Blueprint $table) {
                $table->dropIndex(['post_id', 'status', 'created_at']);
                $table->dropIndex(['status', 'created_at']);
                $table->dropIndex(['author_email']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropIndex(['is_published', 'created_at']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('seo_metas', function (Blueprint $table) {
                $table->dropIndex(['seoable_type', 'seoable_id']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['role']);
                $table->dropIndex(['created_at']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        // Cache 테이블 제거
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }

};
