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
        Schema::create('analytics', function (Blueprint $table) {
            $table->id();
            
            // 관련 모델 참조
            $table->foreignId('post_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            // 이벤트 타입 (page_view, search, download 등)
            $table->enum('type', ['page_view', 'search', 'download', 'click', 'form_submit'])->default('page_view');
            
            // 방문자 정보
            $table->string('ip_address', 45)->index(); // IPv6 지원
            $table->text('user_agent')->nullable();
            $table->string('referer', 1000)->nullable();
            $table->string('session_id', 100)->nullable()->index();
            
            // 검색 관련 (type이 search일 때)
            $table->string('search_query', 255)->nullable()->index();
            $table->integer('search_results_count')->nullable();
            $table->string('search_type', 50)->nullable(); // all, post, page, tag, category
            
            // 페이지 관련
            $table->string('page_url', 1000)->nullable();
            $table->string('page_title', 255)->nullable();
            
            // 브라우저/디바이스 정보 (파싱된 데이터)
            $table->string('browser', 100)->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('device_type', 50)->nullable(); // desktop, mobile, tablet
            
            // 지리적 정보 (선택적)
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            
            // 추가 메타데이터
            $table->json('meta_data')->nullable(); // 추가 정보를 위한 JSON 필드
            
            $table->timestamps();
            
            // 인덱스 설정
            $table->index(['type', 'created_at']); // 타입별 시간순 조회
            $table->index(['post_id', 'created_at']); // 포스트별 시간순 조회
            $table->index(['ip_address', 'post_id', 'created_at']); // 중복 방문 체크용
            $table->index('created_at'); // 날짜별 통계용
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics');
    }
};