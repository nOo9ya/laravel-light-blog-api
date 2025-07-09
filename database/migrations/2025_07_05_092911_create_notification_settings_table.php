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
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            
            // 알림 활성화 여부
            $table->boolean('slack_enabled')->default(false);
            $table->boolean('discord_enabled')->default(false);
            $table->boolean('telegram_enabled')->default(false);
            
            // Slack 설정
            $table->text('slack_webhook_url')->nullable();
            $table->string('slack_channel')->nullable();
            $table->string('slack_username')->default('Laravel Error Bot');
            
            // Discord 설정
            $table->text('discord_webhook_url')->nullable();
            $table->string('discord_username')->default('Laravel Error Bot');
            
            // Telegram 설정
            $table->string('telegram_bot_token')->nullable();
            $table->string('telegram_chat_id')->nullable();
            
            // 알림 레벨 설정 (JSON 배열)
            $table->json('notification_levels')->default('["emergency", "alert", "critical", "error"]');
            
            // 알림 제외 시간 (분)
            $table->integer('throttle_minutes')->default(5);
            
            // 테스트 모드 (개발 환경에서도 알림 전송)
            $table->boolean('test_mode')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
