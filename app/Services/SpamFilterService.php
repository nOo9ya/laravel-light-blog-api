<?php

namespace App\Services;

use App\Models\Comment;
use Illuminate\Support\Str;

class SpamFilterService
{
    /**
     * 스팸 점수 임계값
     */
    protected const SPAM_THRESHOLD = 70;
    protected const MODERATE_THRESHOLD = 50;
    
    /**
     * 스팸 키워드 목록
     */
    protected const SPAM_KEYWORDS = [
        '카지노', '바카라', '포커', '슬롯', '토토', '배팅', 
        '성인', '야동', '스팸', '광고', '홍보', '무료',
        '대출', '투자', '수익', '비트코인', '코인',
        'viagra', 'casino', 'poker', 'sex', 'porn'
    ];
    
    /**
     * 의심스러운 패턴
     */
    protected const SUSPICIOUS_PATTERNS = [
        '/\b[A-Z]{5,}\b/',          // 연속 대문자
        '/(.)\1{4,}/',              // 같은 문자 5번 이상 반복
        '/[!@#$%^&*]{3,}/',         // 특수문자 3개 이상
        '/\d{10,}/',                // 10자리 이상 숫자
        '/(?:http[s]?:\/\/){2,}/',  // 여러 URL
    ];
    
    /**
     * 댓글 스팸 점수 계산
     */
    public static function calculateSpamScore(Comment $comment): array
    {
        $score = 0;
        $reasons = [];
        
        // 1. 키워드 검사
        $keywordScore = self::checkSpamKeywords($comment->content);
        if ($keywordScore > 0) {
            $score += $keywordScore;
            $reasons[] = "스팸 키워드 감지 (+{$keywordScore})";
        }
        
        // 2. 패턴 검사
        $patternScore = self::checkSuspiciousPatterns($comment->content);
        if ($patternScore > 0) {
            $score += $patternScore;
            $reasons[] = "의심스러운 패턴 감지 (+{$patternScore})";
        }
        
        // 3. 링크 검사
        $linkScore = self::checkLinks($comment->detected_links ?? []);
        if ($linkScore > 0) {
            $score += $linkScore;
            $reasons[] = "의심스러운 링크 감지 (+{$linkScore})";
        }
        
        // 4. 길이 검사
        $lengthScore = self::checkContentLength($comment->content);
        if ($lengthScore > 0) {
            $score += $lengthScore;
            $reasons[] = "비정상적인 길이 (+{$lengthScore})";
        }
        
        // 5. 반복 댓글 검사 (같은 IP/이메일)
        $repeatScore = self::checkRepeatComments($comment);
        if ($repeatScore > 0) {
            $score += $repeatScore;
            $reasons[] = "반복 댓글 감지 (+{$repeatScore})";
        }
        
        // 6. 이메일 검사
        if ($comment->is_guest) {
            $emailScore = self::checkEmail($comment->guest_email);
            if ($emailScore > 0) {
                $score += $emailScore;
                $reasons[] = "의심스러운 이메일 (+{$emailScore})";
            }
        }
        
        return [
            'total_score' => min($score, 100), // 최대 100점
            'is_spam' => $score >= self::SPAM_THRESHOLD,
            'needs_moderation' => $score >= self::MODERATE_THRESHOLD,
            'reasons' => $reasons,
            'details' => [
                'keyword_score' => $keywordScore,
                'pattern_score' => $patternScore,
                'link_score' => $linkScore,
                'length_score' => $lengthScore,
                'repeat_score' => $repeatScore,
                'email_score' => $emailScore ?? 0,
            ]
        ];
    }
    
    /**
     * 스팸 키워드 검사
     */
    protected static function checkSpamKeywords(string $content): int
    {
        $score = 0;
        $content = Str::lower($content);
        
        foreach (self::SPAM_KEYWORDS as $keyword) {
            $count = substr_count($content, Str::lower($keyword));
            if ($count > 0) {
                $score += $count * 15; // 키워드 하나당 15점
            }
        }
        
        return min($score, 50); // 최대 50점
    }
    
    /**
     * 의심스러운 패턴 검사
     */
    protected static function checkSuspiciousPatterns(string $content): int
    {
        $score = 0;
        
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $score += 10;
            }
        }
        
        return min($score, 30); // 최대 30점
    }
    
    /**
     * 링크 검사
     */
    protected static function checkLinks(array $links): int
    {
        $score = 0;
        
        // 링크 개수
        if (count($links) > 3) {
            $score += 20;
        } elseif (count($links) > 1) {
            $score += 10;
        }
        
        // 의심스러운 도메인 검사
        $suspiciousDomains = [
            '.tk', '.ml', '.ga', '.cf', // 무료 도메인
            'bit.ly', 'tinyurl.com', 'short.link', // 단축 URL
            'casino', 'poker', 'betting', 'loan' // 의심스러운 키워드 포함
        ];
        
        foreach ($links as $link) {
            $host = parse_url($link, PHP_URL_HOST) ?? '';
            
            foreach ($suspiciousDomains as $domain) {
                if (Str::contains(Str::lower($host), $domain)) {
                    $score += 25;
                    break;
                }
            }
        }
        
        return min($score, 40); // 최대 40점
    }
    
    /**
     * 내용 길이 검사
     */
    protected static function checkContentLength(string $content): int
    {
        $length = mb_strlen($content);
        
        // 너무 짧거나 긴 댓글
        if ($length < 5) {
            return 15;
        } elseif ($length > 1500) {
            return 10;
        }
        
        return 0;
    }
    
    /**
     * 반복 댓글 검사
     */
    protected static function checkRepeatComments(Comment $comment): int
    {
        $score = 0;
        
        // 같은 IP에서 최근 1시간 내 댓글 수
        $recentComments = Comment::where('ip_address', $comment->ip_address)
            ->where('created_at', '>', now()->subHour())
            ->count();
            
        if ($recentComments > 5) {
            $score += 30;
        } elseif ($recentComments > 3) {
            $score += 15;
        }
        
        // 같은 내용의 댓글
        $duplicateComments = Comment::where('content', $comment->content)
            ->where('id', '!=', $comment->id)
            ->where('created_at', '>', now()->subDay())
            ->count();
            
        if ($duplicateComments > 0) {
            $score += 40;
        }
        
        // 비회원 댓글의 경우 이메일로도 검사
        if ($comment->is_guest && $comment->guest_email) {
            $emailComments = Comment::where('guest_email', $comment->guest_email)
                ->where('created_at', '>', now()->subHour())
                ->count();
                
            if ($emailComments > 3) {
                $score += 20;
            }
        }
        
        return min($score, 50); // 최대 50점
    }
    
    /**
     * 이메일 검사
     */
    protected static function checkEmail(string $email): int
    {
        $score = 0;
        
        // 임시 이메일 서비스
        $tempEmailDomains = [
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
            'tempmail.org', 'temp-mail.org', 'disposable.email'
        ];
        
        $domain = Str::after($email, '@');
        
        if (in_array(Str::lower($domain), $tempEmailDomains)) {
            $score += 30;
        }
        
        // 의심스러운 패턴
        if (preg_match('/^[a-z0-9]{20,}@/', $email)) { // 긴 랜덤 문자열
            $score += 15;
        }
        
        if (preg_match('/\d{8,}/', $email)) { // 긴 숫자 포함
            $score += 10;
        }
        
        return min($score, 30); // 최대 30점
    }
    
    /**
     * 자동 조치 수행
     */
    public static function autoModerate(Comment $comment): void
    {
        $spamData = self::calculateSpamScore($comment);
        
        // 스팸 점수 저장
        $comment->update(['spam_score' => $spamData]);
        
        // 자동 조치
        if ($spamData['is_spam']) {
            $comment->markAsSpam();
        } elseif ($spamData['needs_moderation']) {
            $comment->update(['status' => 'pending']);
        }
    }
    
    /**
     * 화이트리스트 이메일 확인
     */
    public static function isWhitelistedEmail(string $email): bool
    {
        // 관리자나 신뢰할 수 있는 이메일 도메인
        $whitelistedDomains = [
            'gmail.com', 'naver.com', 'daum.net', 'hanmail.net',
            'kakao.com', 'nate.com', 'yahoo.com', 'outlook.com'
        ];
        
        $domain = Str::after(Str::lower($email), '@');
        return in_array($domain, $whitelistedDomains);
    }
    
    /**
     * IP 화이트리스트 확인
     */
    public static function isWhitelistedIP(string $ip): bool
    {
        // 관리자나 신뢰할 수 있는 IP 주소
        $whitelistedIPs = [
            '127.0.0.1', '::1', // 로컬
            // 추가 화이트리스트 IP들
        ];
        
        return in_array($ip, $whitelistedIPs);
    }
    
    /**
     * 스팸 통계 조회
     */
    public static function getSpamStats(): array
    {
        $totalComments = Comment::count();
        $spamComments = Comment::spam()->count();
        $pendingComments = Comment::pending()->count();
        $approvedComments = Comment::approved()->count();
        
        return [
            'total' => $totalComments,
            'spam' => $spamComments,
            'pending' => $pendingComments,
            'approved' => $approvedComments,
            'spam_rate' => $totalComments > 0 ? round(($spamComments / $totalComments) * 100, 2) : 0,
        ];
    }
}