<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * API 응답 구조를 표준화하는 트레이트
 */
trait ApiResponse
{
    /**
     * 성공 응답 반환
     */
    protected function successResponse($data = null, string $message = '성공적으로 처리되었습니다', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * 에러 응답 반환
     */
    protected function errorResponse(string $code, string $message, $details = null, int $statusCode = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * 생성 성공 응답 반환
     */
    protected function createdResponse($data = null, string $message = '성공적으로 생성되었습니다'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * 삭제 성공 응답 반환
     */
    protected function deletedResponse(string $message = '성공적으로 삭제되었습니다'): JsonResponse
    {
        return $this->successResponse(null, $message, 200);
    }

    /**
     * 수정 성공 응답 반환
     */
    protected function updatedResponse($data = null, string $message = '성공적으로 수정되었습니다'): JsonResponse
    {
        return $this->successResponse($data, $message, 200);
    }

    /**
     * 페이지네이션 응답 반환
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, string $resourceClass = null, string $message = '목록 조회가 완료되었습니다'): JsonResponse
    {
        $data = $resourceClass ? $resourceClass::collection($paginator) : $paginator->items();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'path' => $paginator->path(),
                'first_page_url' => $paginator->url(1),
                'last_page_url' => $paginator->url($paginator->lastPage()),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
            'message' => $message
        ]);
    }

    /**
     * 인증 실패 응답 반환
     */
    protected function unauthorizedResponse(string $message = '인증이 필요합니다'): JsonResponse
    {
        return $this->errorResponse('UNAUTHORIZED', $message, null, 401);
    }

    /**
     * 권한 부족 응답 반환
     */
    protected function forbiddenResponse(string $message = '권한이 부족합니다'): JsonResponse
    {
        return $this->errorResponse('FORBIDDEN', $message, null, 403);
    }

    /**
     * 리소스 없음 응답 반환
     */
    protected function notFoundResponse(string $message = '요청한 리소스를 찾을 수 없습니다'): JsonResponse
    {
        return $this->errorResponse('NOT_FOUND', $message, null, 404);
    }

    /**
     * 유효성 검사 실패 응답 반환
     */
    protected function validationErrorResponse($errors, string $message = '입력 데이터가 올바르지 않습니다'): JsonResponse
    {
        return $this->errorResponse('VALIDATION_ERROR', $message, $errors, 422);
    }

    /**
     * 서버 에러 응답 반환
     */
    protected function serverErrorResponse(string $message = '서버 내부 오류가 발생했습니다'): JsonResponse
    {
        return $this->errorResponse('INTERNAL_SERVER_ERROR', $message, null, 500);
    }

    /**
     * 중복 리소스 응답 반환
     */
    protected function duplicateResponse(string $message = '이미 존재하는 리소스입니다'): JsonResponse
    {
        return $this->errorResponse('DUPLICATE_RESOURCE', $message, null, 409);
    }

    /**
     * 비즈니스 로직 에러 응답 반환
     */
    protected function businessErrorResponse(string $code, string $message, $details = null): JsonResponse
    {
        return $this->errorResponse($code, $message, $details, 400);
    }

    /**
     * 임시적으로 사용할 수 없는 서비스 응답 반환
     */
    protected function serviceUnavailableResponse(string $message = '서비스를 일시적으로 사용할 수 없습니다'): JsonResponse
    {
        return $this->errorResponse('SERVICE_UNAVAILABLE', $message, null, 503);
    }

    /**
     * Rate Limit 초과 응답 반환
     */
    protected function rateLimitResponse(string $message = '요청 한도를 초과했습니다'): JsonResponse
    {
        return $this->errorResponse('RATE_LIMIT_EXCEEDED', $message, null, 429);
    }
}