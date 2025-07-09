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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            
            // 포스트 관계
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            
            // 사용자 정보 (회원/비회원 구분)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('guest_name', 50)->nullable(); // 비회원 이름
            $table->string('guest_email', 100)->nullable(); // 비회원 이메일
            $table->string('guest_password')->nullable(); // 비회원 비밀번호 (해시)
            
            // 대댓글 지원 (계층형 구조)
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->unsignedInteger('depth')->default(0); // 댓글 깊이 (0: 최상위, 1: 대댓글, 2: 대대댓글...)
            $table->text('path')->nullable(); // 계층 경로 (예: /1/3/5)
            
            // 댓글 내용
            $table->text('content');
            $table->text('content_html')->nullable(); // HTML 변환된 내용 (링크, 줄바꿈 등)
            
            // 상태 관리
            $table->enum('status', ['approved', 'pending', 'spam', 'deleted'])->default('pending');
            
            // 메타 정보
            $table->ipAddress('ip_address')->nullable(); // 작성자 IP
            $table->text('user_agent')->nullable(); // 브라우저 정보
            $table->json('spam_score')->nullable(); // 스팸 점수 및 분석 결과
            
            // 소셜 링크 감지 (OG 정보 자동 추출용)
            $table->json('detected_links')->nullable(); // 댓글 내 감지된 링크들
            $table->json('og_data')->nullable(); // 자동 추출된 OG 정보
            
            // 관리 정보
            $table->timestamp('approved_at')->nullable(); // 승인 시간
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null'); // 승인자
            
            $table->timestamps();
            
            // 인덱스
            $table->index(['post_id', 'status', 'created_at']);
            $table->index(['parent_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
