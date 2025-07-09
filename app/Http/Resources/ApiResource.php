<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 공통 API 응답 구조를 위한 베이스 리소스 클래스
 */
abstract class ApiResource extends JsonResource
{
    /**
     * 성공 응답 구조로 래핑
     */
    public function toResponse($request)
    {
        return parent::toResponse($request)->setData([
            'success' => true,
            'data' => $this->resource,
            'message' => $this->getMessage()
        ]);
    }

    /**
     * 응답 메시지 반환 (하위 클래스에서 재정의 가능)
     */
    protected function getMessage(): string
    {
        return '조회가 완료되었습니다';
    }

    /**
     * 페이지네이션을 포함한 성공 응답 구조
     */
    public static function collection($resource)
    {
        return parent::collection($resource)->additional([
            'success' => true,
            'message' => '목록 조회가 완료되었습니다'
        ]);
    }
}