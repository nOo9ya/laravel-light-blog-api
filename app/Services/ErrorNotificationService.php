<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\NotificationSetting;
use Carbon\Carbon;

class ErrorNotificationService
{
    private NotificationSetting $settings;

    public function __construct()
    {
        $this->settings = NotificationSetting::getSettings();
    }

    /**
     * 활성화된 알림 서비스 확인
     */
    public function getEnabledServices(): array
    {
        return $this->settings->getEnabledServices();
    }

    /**
     * 설정 새로고침
     */
    public function refreshSettings(): void
    {
        $this->settings = NotificationSetting::getSettings();
    }

    /**
     * 에러 알림 전송 (중복 방지)
     */
    public function sendErrorNotification(string $level, string $message, string $file = '', int $line = 0, string $stackTrace = ''): bool
    {
        if (!$this->shouldSendNotification($level)) {
            return false;
        }

        // 중복 알림 방지
        $cacheKey = 'error_notification_' . md5($message . $file . $line);
        if (Cache::has($cacheKey)) {
            return false;
        }

        // 설정된 쓰로틀 시간만큼 중복 방지
        $throttleMinutes = $this->settings->throttle_minutes ?? 5;
        Cache::put($cacheKey, true, $throttleMinutes * 60);

        $success = false;
        $errorData = [
            'level' => $level,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'stack_trace' => $stackTrace,
            'timestamp' => Carbon::now()->toISOString(),
            'app_name' => config('app.name', 'Laravel Blog'),
            'app_url' => config('app.url', 'http://localhost')
        ];

        // Slack 알림
        if ($this->settings->isSlackConfigured()) {
            $success = $this->sendSlackNotification($errorData) || $success;
        }

        // Discord 알림
        if ($this->settings->isDiscordConfigured()) {
            $success = $this->sendDiscordNotification($errorData) || $success;
        }

        // Telegram 알림
        if ($this->settings->isTelegramConfigured()) {
            $success = $this->sendTelegramNotification($errorData) || $success;
        }

        return $success;
    }

    /**
     * 알림 전송 여부 판단
     */
    private function shouldSendNotification(string $level): bool
    {
        // 환경 확인 (프로덕션 또는 테스트 모드)
        if (!app()->environment('production') && !$this->settings->test_mode) {
            return false;
        }

        // 활성화된 서비스가 있는지 확인
        if (!$this->settings->hasAnyServiceEnabled()) {
            return false;
        }

        // 설정된 알림 레벨인지 확인
        return $this->settings->shouldNotifyForLevel($level);
    }

    /**
     * Slack 알림 전송
     */
    private function sendSlackNotification(array $errorData): bool
    {
        try {
            $color = match (strtolower($errorData['level'])) {
                'emergency', 'alert', 'critical' => 'danger',
                'error' => 'warning',
                default => 'good'
            };

            $payload = [
                'text' => '🚨 ' . $errorData['app_name'] . ' 에러 발생',
                'username' => $this->settings->slack_username ?? 'Laravel Error Bot',
                'channel' => $this->settings->slack_channel,
                'attachments' => [
                    [
                        'color' => $color,
                        'title' => strtoupper($errorData['level']) . ' 오류',
                        'text' => $errorData['message'],
                        'fields' => [
                            [
                                'title' => '파일',
                                'value' => $errorData['file'] . ':' . $errorData['line'],
                                'short' => true
                            ],
                            [
                                'title' => '시간',
                                'value' => Carbon::parse($errorData['timestamp'])->format('Y-m-d H:i:s'),
                                'short' => true
                            ],
                            [
                                'title' => '앱',
                                'value' => $errorData['app_name'],
                                'short' => true
                            ],
                            [
                                'title' => 'URL',
                                'value' => $errorData['app_url'],
                                'short' => true
                            ]
                        ],
                        'footer' => 'Laravel Error Monitor',
                        'ts' => Carbon::parse($errorData['timestamp'])->timestamp
                    ]
                ]
            ];

            $response = Http::timeout(10)->post($this->settings->slack_webhook_url, $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Discord 알림 전송
     */
    private function sendDiscordNotification(array $errorData): bool
    {
        try {
            $color = match (strtolower($errorData['level'])) {
                'emergency', 'alert', 'critical' => 0xFF0000, // 빨간색
                'error' => 0xFF6600, // 주황색
                default => 0x00FF00 // 초록색
            };

            $payload = [
                'username' => $this->settings->discord_username ?? 'Laravel Error Bot',
                'embeds' => [
                    [
                        'title' => '🚨 ' . $errorData['app_name'] . ' 에러 발생',
                        'color' => $color,
                        'fields' => [
                            [
                                'name' => '레벨',
                                'value' => strtoupper($errorData['level']),
                                'inline' => true
                            ],
                            [
                                'name' => '메시지',
                                'value' => substr($errorData['message'], 0, 1000),
                                'inline' => false
                            ],
                            [
                                'name' => '파일',
                                'value' => $errorData['file'] . ':' . $errorData['line'],
                                'inline' => true
                            ],
                            [
                                'name' => '시간',
                                'value' => Carbon::parse($errorData['timestamp'])->format('Y-m-d H:i:s'),
                                'inline' => true
                            ]
                        ],
                        'footer' => [
                            'text' => 'Laravel Error Monitor'
                        ],
                        'timestamp' => $errorData['timestamp']
                    ]
                ]
            ];

            $response = Http::timeout(10)->post($this->settings->discord_webhook_url, $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send Discord notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Telegram 알림 전송
     */
    private function sendTelegramNotification(array $errorData): bool
    {
        try {
            $emoji = match (strtolower($errorData['level'])) {
                'emergency', 'alert', 'critical' => '🆘',
                'error' => '❌',
                default => '⚠️'
            };

            $message = sprintf(
                "%s *%s 에러 발생*\n\n" .
                "🔴 *레벨*: %s\n" .
                "💬 *메시지*: %s\n" .
                "📁 *파일*: %s:%d\n" .
                "🕒 *시간*: %s\n" .
                "🌐 *앱*: %s",
                $emoji,
                $errorData['app_name'],
                strtoupper($errorData['level']),
                substr($errorData['message'], 0, 500),
                basename($errorData['file']),
                $errorData['line'],
                Carbon::parse($errorData['timestamp'])->format('Y-m-d H:i:s'),
                $errorData['app_name']
            );

            $payload = [
                'chat_id' => $this->settings->telegram_chat_id,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true
            ];

            $url = "https://api.telegram.org/bot{$this->settings->telegram_bot_token}/sendMessage";
            $response = Http::timeout(10)->post($url, $payload);
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 테스트 알림 전송
     */
    public function sendTestNotification(): array
    {
        $results = [];
        $errorData = [
            'level' => 'info',
            'message' => '테스트 알림입니다. 에러 알림 시스템이 정상적으로 작동합니다.',
            'file' => 'test.php',
            'line' => 1,
            'stack_trace' => '',
            'timestamp' => Carbon::now()->toISOString(),
            'app_name' => config('app.name', 'Laravel Blog'),
            'app_url' => config('app.url', 'http://localhost')
        ];

        if ($this->settings->isSlackConfigured()) {
            $results['slack'] = $this->sendSlackNotification($errorData);
        }

        if ($this->settings->isDiscordConfigured()) {
            $results['discord'] = $this->sendDiscordNotification($errorData);
        }

        if ($this->settings->isTelegramConfigured()) {
            $results['telegram'] = $this->sendTelegramNotification($errorData);
        }

        return $results;
    }
}