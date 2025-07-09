<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tags 테이블 인덱스 최적화
        try {
            Schema::table('tags', function (Blueprint $table) {
                $table->index(['name']);
                $table->index(['color']);
                $table->index(['created_at']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Settings 테이블 인덱스 최적화
        try {
            Schema::table('settings', function (Blueprint $table) {
                $table->index(['type']);
                $table->index(['key']);
                $table->index(['type', 'key']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Post_attachments 테이블 인덱스 최적화
        try {
            Schema::table('post_attachments', function (Blueprint $table) {
                $table->index(['post_id', 'created_at']);
                $table->index(['mime_type']);
                $table->index(['size']);
                $table->index(['file_type']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Analytics 테이블 인덱스 최적화
        try {
            Schema::table('analytics', function (Blueprint $table) {
                $table->index(['session_id', 'created_at']);
                $table->index(['event_type', 'created_at']);
                $table->index(['user_id', 'created_at']);
                $table->index(['ip_address']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Users 테이블 추가 인덱스
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['email_verified_at']);
                $table->index(['role', 'created_at']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Comments 테이블 추가 인덱스
        try {
            Schema::table('comments', function (Blueprint $table) {
                $table->index(['parent_id', 'status', 'created_at']);
                $table->index(['depth']);
                $table->index(['ip_address']);
                $table->index(['user_id', 'created_at']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Posts 테이블 추가 인덱스 (검색 성능 향상)
        try {
            Schema::table('posts', function (Blueprint $table) {
                $table->index(['status', 'published_at', 'views_count']);
                $table->index(['featured', 'status', 'published_at']);
                $table->index(['meta_title']);
                $table->index(['meta_description']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Pages 테이블 추가 인덱스
        try {
            Schema::table('pages', function (Blueprint $table) {
                $table->index(['category_id', 'is_published']);
                $table->index(['template']);
                $table->index(['menu_order']);
                $table->index(['views_count']);
            });
        } catch (\Exception $e) {
            // 이미 존재하는 인덱스 무시
        }

        // Full-text search indexes for better content search
        try {
            DB::statement('ALTER TABLE posts ADD FULLTEXT(title, summary, content)');
            DB::statement('ALTER TABLE pages ADD FULLTEXT(title, excerpt, content)');
            DB::statement('ALTER TABLE comments ADD FULLTEXT(content)');
        } catch (\Exception $e) {
            // Full-text 인덱스 생성 실패 시 무시 (MyISAM 엔진 필요)
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 인덱스 제거 (존재하지 않을 수 있으므로 try-catch 사용)
        try {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropIndex(['name']);
                $table->dropIndex(['color']);
                $table->dropIndex(['created_at']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropIndex(['key']);
                $table->dropIndex(['type', 'key']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('post_attachments', function (Blueprint $table) {
                $table->dropIndex(['post_id', 'created_at']);
                $table->dropIndex(['mime_type']);
                $table->dropIndex(['size']);
                $table->dropIndex(['file_type']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('analytics', function (Blueprint $table) {
                $table->dropIndex(['session_id', 'created_at']);
                $table->dropIndex(['event_type', 'created_at']);
                $table->dropIndex(['user_id', 'created_at']);
                $table->dropIndex(['ip_address']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['email_verified_at']);
                $table->dropIndex(['role', 'created_at']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('comments', function (Blueprint $table) {
                $table->dropIndex(['parent_id', 'status', 'created_at']);
                $table->dropIndex(['depth']);
                $table->dropIndex(['ip_address']);
                $table->dropIndex(['user_id', 'created_at']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropIndex(['status', 'published_at', 'views_count']);
                $table->dropIndex(['featured', 'status', 'published_at']);
                $table->dropIndex(['meta_title']);
                $table->dropIndex(['meta_description']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        try {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropIndex(['category_id', 'is_published']);
                $table->dropIndex(['template']);
                $table->dropIndex(['menu_order']);
                $table->dropIndex(['views_count']);
            });
        } catch (\Exception $e) {
            // 인덱스가 존재하지 않을 수 있음
        }

        // Full-text search indexes 제거
        try {
            DB::statement('ALTER TABLE posts DROP INDEX title');
            DB::statement('ALTER TABLE pages DROP INDEX title');
            DB::statement('ALTER TABLE comments DROP INDEX content');
        } catch (\Exception $e) {
            // Full-text 인덱스 제거 실패 시 무시
        }
    }
};