<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'slack_enabled',
        'discord_enabled', 
        'telegram_enabled',
        'slack_webhook_url',
        'slack_channel',
        'slack_username',
        'discord_webhook_url',
        'discord_username',
        'telegram_bot_token',
        'telegram_chat_id',
        'notification_levels',
        'throttle_minutes',
        'test_mode'
    ];

    protected $casts = [
        'slack_enabled' => 'boolean',
        'discord_enabled' => 'boolean',
        'telegram_enabled' => 'boolean',
        'test_mode' => 'boolean',
        'notification_levels' => 'array',
        'throttle_minutes' => 'integer'
    ];

    /**
     * 싱글톤 패턴으로 설정 조회 (설정은 하나만 존재)
     */
    public static function getSettings(): self
    {
        $settings = self::first();
        
        if (!$settings) {
            $settings = self::create([
                'slack_enabled' => false,
                'discord_enabled' => false,
                'telegram_enabled' => false,
                'notification_levels' => ['emergency', 'alert', 'critical', 'error'],
                'throttle_minutes' => 5,
                'test_mode' => false
            ]);
        }
        
        return $settings;
    }

    /**
     * 활성화된 서비스 목록
     */
    public function getEnabledServices(): array
    {
        return [
            'slack' => $this->slack_enabled && !empty($this->slack_webhook_url),
            'discord' => $this->discord_enabled && !empty($this->discord_webhook_url),
            'telegram' => $this->telegram_enabled && !empty($this->telegram_bot_token) && !empty($this->telegram_chat_id),
            'any_enabled' => $this->hasAnyServiceEnabled()
        ];
    }

    /**
     * 하나라도 활성화된 서비스가 있는지 확인
     */
    public function hasAnyServiceEnabled(): bool
    {
        return ($this->slack_enabled && !empty($this->slack_webhook_url)) ||
               ($this->discord_enabled && !empty($this->discord_webhook_url)) ||
               ($this->telegram_enabled && !empty($this->telegram_bot_token) && !empty($this->telegram_chat_id));
    }

    /**
     * 특정 에러 레벨이 알림 대상인지 확인
     */
    public function shouldNotifyForLevel(string $level): bool
    {
        return in_array(strtolower($level), $this->notification_levels ?? []);
    }

    /**
     * Slack 설정 유효성 검사
     */
    public function isSlackConfigured(): bool
    {
        return $this->slack_enabled && !empty($this->slack_webhook_url);
    }

    /**
     * Discord 설정 유효성 검사
     */
    public function isDiscordConfigured(): bool
    {
        return $this->discord_enabled && !empty($this->discord_webhook_url);
    }

    /**
     * Telegram 설정 유효성 검사
     */
    public function isTelegramConfigured(): bool
    {
        return $this->telegram_enabled && 
               !empty($this->telegram_bot_token) && 
               !empty($this->telegram_chat_id);
    }

    /**
     * 설정 업데이트 (유효성 검사 포함)
     */
    public function updateSettings(array $data): bool
    {
        // 민감한 정보는 암호화하여 저장할 수도 있음
        $validated = $this->validateSettings($data);
        
        if ($validated['valid']) {
            return $this->update($validated['data']);
        }
        
        return false;
    }

    /**
     * 설정 데이터 유효성 검사
     */
    private function validateSettings(array $data): array
    {
        $errors = [];
        $cleanData = [];

        // Boolean 필드 처리
        $booleanFields = ['slack_enabled', 'discord_enabled', 'telegram_enabled', 'test_mode'];
        foreach ($booleanFields as $field) {
            $cleanData[$field] = isset($data[$field]) && $data[$field];
        }

        // Slack 설정 검증
        if ($cleanData['slack_enabled']) {
            if (empty($data['slack_webhook_url'])) {
                $errors[] = 'Slack Webhook URL이 필요합니다.';
            } elseif (!filter_var($data['slack_webhook_url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Slack Webhook URL 형식이 올바르지 않습니다.';
            } else {
                $cleanData['slack_webhook_url'] = $data['slack_webhook_url'];
                $cleanData['slack_channel'] = $data['slack_channel'] ?? null;
                $cleanData['slack_username'] = $data['slack_username'] ?? 'Laravel Error Bot';
            }
        }

        // Discord 설정 검증
        if ($cleanData['discord_enabled']) {
            if (empty($data['discord_webhook_url'])) {
                $errors[] = 'Discord Webhook URL이 필요합니다.';
            } elseif (!filter_var($data['discord_webhook_url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Discord Webhook URL 형식이 올바르지 않습니다.';
            } else {
                $cleanData['discord_webhook_url'] = $data['discord_webhook_url'];
                $cleanData['discord_username'] = $data['discord_username'] ?? 'Laravel Error Bot';
            }
        }

        // Telegram 설정 검증
        if ($cleanData['telegram_enabled']) {
            if (empty($data['telegram_bot_token'])) {
                $errors[] = 'Telegram Bot Token이 필요합니다.';
            } elseif (empty($data['telegram_chat_id'])) {
                $errors[] = 'Telegram Chat ID가 필요합니다.';
            } else {
                $cleanData['telegram_bot_token'] = $data['telegram_bot_token'];
                $cleanData['telegram_chat_id'] = $data['telegram_chat_id'];
            }
        }

        // 알림 레벨 설정
        if (isset($data['notification_levels']) && is_array($data['notification_levels'])) {
            $validLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
            $cleanData['notification_levels'] = array_intersect($data['notification_levels'], $validLevels);
        }

        // 쓰로틀 시간
        $cleanData['throttle_minutes'] = max(1, intval($data['throttle_minutes'] ?? 5));

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $cleanData
        ];
    }
}