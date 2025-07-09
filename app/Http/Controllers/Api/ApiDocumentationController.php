<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Info(
 *     title="Laravel Light Blog API",
 *     version="1.0.0",
 *     description="Laravel 기반의 경량 블로그 API 서비스",
 *     @OA\Contact(
 *         email="admin@example.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api/v1",
 *     description="API v1"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="JWT Bearer Token을 입력하세요"
 * )
 *
 * @OA\Schema(
 *     schema="ApiResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="요청이 성공적으로 처리되었습니다"),
 *     @OA\Property(property="data", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="ApiError",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(
 *         property="error",
 *         type="object",
 *         @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
 *         @OA\Property(property="message", type="string", example="입력값이 올바르지 않습니다"),
 *         @OA\Property(property="details", type="object")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="from", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=10),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="to", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=150)
 * )
 */
class ApiDocumentationController extends Controller
{
    /**
     * API 문서 정보 반환
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'name' => 'Laravel Light Blog API',
            'version' => '1.0.0',
            'description' => 'Laravel 기반의 경량 블로그 API 서비스',
            'endpoints' => [
                'health' => '/api/v1/system/health',
                'info' => '/api/v1/system/info',
                'documentation' => '/api/documentation'
            ],
            'authentication' => 'JWT Bearer Token',
            'support' => 'admin@example.com'
        ]);
    }
}