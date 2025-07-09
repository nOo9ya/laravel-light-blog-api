<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ErrorLogService
{
    private string $logPath;
    private int $maxLines = 1000;

    public function __construct()
    {
        $this->logPath = storage_path('logs/laravel.log');
    }

    /**
     * 최근 에러 로그 가져오기
     */
    public function getRecentErrorLogs(int $limit = 100): array
    {
        if (!File::exists($this->logPath)) {
            return [];
        }

        $logs = [];
        $handle = fopen($this->logPath, 'r');
        
        if (!$handle) {
            return [];
        }

        // 파일 끝에서부터 읽기
        fseek($handle, -1, SEEK_END);
        $lines = [];
        $buffer = '';
        
        while (ftell($handle) > 0 && count($lines) < $this->maxLines) {
            $char = fgetc($handle);
            if ($char === "\n") {
                $lines[] = strrev($buffer);
                $buffer = '';
            } else {
                $buffer .= $char;
            }
            fseek($handle, -2, SEEK_CUR);
        }
        
        if ($buffer) {
            $lines[] = strrev($buffer);
        }
        
        fclose($handle);
        
        // 로그 파싱
        $currentLog = null;
        $logEntries = [];
        
        foreach (array_reverse($lines) as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)/', $line, $matches)) {
                // 이전 로그 저장
                if ($currentLog && count($logEntries) < $limit) {
                    $logEntries[] = $currentLog;
                }
                
                // 새 로그 시작
                $currentLog = [
                    'timestamp' => $matches[1],
                    'environment' => $matches[2],
                    'level' => $matches[3],
                    'message' => $matches[4],
                    'stack_trace' => '',
                    'parsed_date' => Carbon::parse($matches[1]),
                    'level_class' => $this->getLevelClass($matches[3])
                ];
            } elseif ($currentLog && !empty(trim($line))) {
                // 스택 트레이스 또는 추가 정보
                $currentLog['stack_trace'] .= $line . "\n";
            }
        }
        
        // 마지막 로그 저장
        if ($currentLog && count($logEntries) < $limit) {
            $logEntries[] = $currentLog;
        }
        
        return array_slice($logEntries, 0, $limit);
    }

    /**
     * 에러 레벨별 통계
     */
    public function getErrorStatistics(): array
    {
        $logs = $this->getRecentErrorLogs(500);
        $stats = [
            'total' => count($logs),
            'emergency' => 0,
            'alert' => 0,
            'critical' => 0,
            'error' => 0,
            'warning' => 0,
            'notice' => 0,
            'info' => 0,
            'debug' => 0,
            'recent_errors' => 0,
            'last_error' => null
        ];

        $oneHourAgo = Carbon::now()->subHour();
        
        foreach ($logs as $log) {
            $level = strtolower($log['level']);
            if (isset($stats[$level])) {
                $stats[$level]++;
            }
            
            // 최근 1시간 내 에러
            if ($log['parsed_date']->gt($oneHourAgo) && in_array($level, ['emergency', 'alert', 'critical', 'error'])) {
                $stats['recent_errors']++;
            }
            
            // 최근 에러 시간
            if (in_array($level, ['emergency', 'alert', 'critical', 'error']) && !$stats['last_error']) {
                $stats['last_error'] = $log['parsed_date'];
            }
        }

        return $stats;
    }

    /**
     * 로그 레벨에 따른 CSS 클래스
     */
    private function getLevelClass(string $level): string
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical' => 'text-red-600 bg-red-50',
            'error' => 'text-red-500 bg-red-50',
            'warning' => 'text-yellow-600 bg-yellow-50',
            'notice', 'info' => 'text-blue-600 bg-blue-50',
            'debug' => 'text-gray-600 bg-gray-50',
            default => 'text-gray-800 bg-gray-50'
        };
    }

    /**
     * 특정 에러 패턴 검색
     */
    public function searchErrors(string $pattern, int $limit = 50): array
    {
        $logs = $this->getRecentErrorLogs(500);
        $results = [];
        
        foreach ($logs as $log) {
            if (stripos($log['message'], $pattern) !== false || 
                stripos($log['stack_trace'], $pattern) !== false) {
                $results[] = $log;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        
        return $results;
    }

    /**
     * 로그 파일 크기 확인
     */
    public function getLogFileInfo(): array
    {
        if (!File::exists($this->logPath)) {
            return [
                'exists' => false,
                'size' => 0,
                'size_human' => '0 B',
                'modified' => null
            ];
        }

        $size = File::size($this->logPath);
        $modified = Carbon::createFromTimestamp(File::lastModified($this->logPath));

        return [
            'exists' => true,
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'modified' => $modified
        ];
    }

    /**
     * 바이트를 읽기 쉬운 형식으로 변환
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 에러 로그 삭제 (로그 로테이션)
     */
    public function clearErrorLogs(): bool
    {
        try {
            if (File::exists($this->logPath)) {
                File::put($this->logPath, '');
                Log::info('Error logs cleared by admin');
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to clear error logs: ' . $e->getMessage());
            return false;
        }
    }
}