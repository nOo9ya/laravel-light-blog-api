<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * 한글 및 다국어 슬러그 생성 서비스
 * 
 * @OA\Schema(
 *     schema="SlugService",
 *     description="한글 슬러그 생성 서비스"
 * )
 */
class SlugService
{
    /**
     * 한글 제목을 슬러그로 변환
     * 
     * @param string $title 변환할 제목
     * @param string $separator 구분자 (기본값: '-')
     * @return string 생성된 슬러그
     * 
     * @OA\Schema(
     *     type="object",
     *     @OA\Property(property="title", type="string", description="변환할 제목"),
     *     @OA\Property(property="separator", type="string", description="구분자")
     * )
     */
    public static function generateKoreanSlug(string $title, string $separator = '-'): string
    {
        // 1. 기본 정리: 앞뒤 공백 제거
        $slug = trim($title);
        
        // 2. 연속된 공백을 하나로 통합
        $slug = preg_replace('/\s+/', ' ', $slug);
        
        // 3. 특수문자 제거 (한글, 영문, 숫자, 공백만 허용)
        $slug = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $slug);
        
        // 4. 공백을 구분자로 변경
        $slug = str_replace(' ', $separator, $slug);
        
        // 5. 연속된 구분자를 하나로 통합
        $slug = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $slug);
        
        // 6. 앞뒤 구분자 제거
        $slug = trim($slug, $separator);
        
        // 7. 소문자 변환 (영문만)
        $slug = mb_strtolower($slug);
        
        // 8. 빈 문자열이면 랜덤 문자열 생성
        if (empty($slug)) {
            $slug = 'page-' . Str::random(8);
        }
        
        return $slug;
    }
    
    /**
     * 영문 제목을 슬러그로 변환 (Laravel 기본 방식)
     * 
     * @param string $title 변환할 제목
     * @param string $separator 구분자
     * @return string 생성된 슬러그
     */
    public static function generateEnglishSlug(string $title, string $separator = '-'): string
    {
        return Str::slug($title, $separator);
    }
    
    /**
     * 자동 언어 감지하여 적절한 슬러그 생성
     * 
     * @param string $title 변환할 제목
     * @param string $separator 구분자
     * @return string 생성된 슬러그
     */
    public static function generateAutoSlug(string $title, string $separator = '-'): string
    {
        // 한글이 포함되어 있는지 확인
        if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $title)) {
            return self::generateKoreanSlug($title, $separator);
        }
        
        // 한글이 없으면 영문 슬러그 생성
        return self::generateEnglishSlug($title, $separator);
    }
    
    /**
     * 슬러그 중복 확인 및 고유 슬러그 생성
     * 
     * @param string $baseSlug 기본 슬러그
     * @param string $modelClass 모델 클래스
     * @param int|null $excludeId 제외할 ID (수정 시 현재 레코드 제외)
     * @return string 고유한 슬러그
     */
    public static function makeUniqueSlug(
        string $baseSlug, 
        string $modelClass, 
        ?int $excludeId = null
    ): string {
        $originalSlug = $baseSlug;
        $counter = 1;
        
        while (self::slugExists($baseSlug, $modelClass, $excludeId)) {
            $baseSlug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $baseSlug;
    }
    
    /**
     * 슬러그 존재 여부 확인
     * 
     * @param string $slug 확인할 슬러그
     * @param string $modelClass 모델 클래스
     * @param int|null $excludeId 제외할 ID
     * @return bool 존재 여부
     */
    public static function slugExists(string $slug, string $modelClass, ?int $excludeId = null): bool
    {
        $query = $modelClass::where('slug', $slug);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }
    
    /**
     * 슬러그 유효성 검사
     * 
     * @param string $slug 검사할 슬러그
     * @return array 검사 결과
     */
    public static function validateSlug(string $slug): array
    {
        $errors = [];
        
        // 길이 확인 (3-100자)
        if (strlen($slug) < 3) {
            $errors[] = '슬러그는 최소 3자 이상이어야 합니다.';
        }
        
        if (strlen($slug) > 100) {
            $errors[] = '슬러그는 최대 100자까지 가능합니다.';
        }
        
        // 허용되지 않는 문자 확인
        if (!preg_match('/^[\p{L}\p{N}\-_]+$/u', $slug)) {
            $errors[] = '슬러그는 한글, 영문, 숫자, 하이픈(-), 언더스코어(_)만 사용 가능합니다.';
        }
        
        // 시작과 끝이 구분자인지 확인
        if (preg_match('/^[-_]|[-_]$/', $slug)) {
            $errors[] = '슬러그는 하이픈이나 언더스코어로 시작하거나 끝날 수 없습니다.';
        }
        
        // 연속된 구분자 확인
        if (preg_match('/[-_]{2,}/', $slug)) {
            $errors[] = '슬러그에 연속된 구분자를 사용할 수 없습니다.';
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * 슬러그 미리보기 생성
     * 
     * @param string $title 제목
     * @param array $options 옵션
     * @return array 미리보기 결과
     */
    public static function previewSlug(string $title, array $options = []): array
    {
        $separator = $options['separator'] ?? '-';
        $method = $options['method'] ?? 'auto'; // auto, korean, english
        
        switch ($method) {
            case 'korean':
                $slug = self::generateKoreanSlug($title, $separator);
                break;
            case 'english':
                $slug = self::generateEnglishSlug($title, $separator);
                break;
            default:
                $slug = self::generateAutoSlug($title, $separator);
        }
        
        $validation = self::validateSlug($slug);
        
        return [
            'original_title' => $title,
            'generated_slug' => $slug,
            'method_used' => $method,
            'separator' => $separator,
            'validation' => $validation,
            'url_preview' => url("/posts/{$slug}"),
            'character_count' => mb_strlen($slug),
            'contains_korean' => preg_match('/[\x{AC00}-\x{D7AF}]/u', $slug) ? true : false
        ];
    }
}