<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class IpRestrictMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 허용된 IP 주소 목록
        $allowedIps = config('app.allowed_ips', []);
        
        // IP 제한이 설정되지 않은 경우 모든 IP 허용
        if (empty($allowedIps)) {
            return $next($request);
        }

        $clientIp = $this->getClientIp($request);
        
        // 허용된 IP 목록에 없는 경우 접근 차단
        if (!$this->isIpAllowed($clientIp, $allowedIps)) {
            return $this->accessDeniedResponse($clientIp);
        }

        return $next($request);
    }

    /**
     * 클라이언트의 실제 IP 주소를 가져옵니다
     */
    private function getClientIp(Request $request): string
    {
        // 프록시나 로드밸런서를 통한 요청의 경우 실제 IP 확인
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',      // Cloudflare
            'HTTP_CLIENT_IP',             // 프록시
            'HTTP_X_FORWARDED_FOR',       // 로드밸런서
            'HTTP_X_FORWARDED',           
            'HTTP_X_CLUSTER_CLIENT_IP',   
            'HTTP_FORWARDED_FOR',         
            'HTTP_FORWARDED',             
            'REMOTE_ADDR'                 // 기본 IP
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    /**
     * IP가 허용된 목록에 있는지 확인합니다
     */
    private function isIpAllowed(string $clientIp, array $allowedIps): bool
    {
        foreach ($allowedIps as $allowedIp) {
            // CIDR 표기법 지원 (예: 192.168.1.0/24)
            if (str_contains($allowedIp, '/')) {
                if ($this->ipInRange($clientIp, $allowedIp)) {
                    return true;
                }
            } else {
                // 정확한 IP 매칭
                if ($clientIp === $allowedIp) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * IP가 CIDR 범위 내에 있는지 확인합니다
     */
    private function ipInRange(string $ip, string $cidr): bool
    {
        list($network, $mask) = explode('/', $cidr);
        
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || 
            !filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        $maskLong = -1 << (32 - (int)$mask);

        return ($ipLong & $maskLong) === ($networkLong & $maskLong);
    }

    /**
     * 접근 거부 응답을 반환합니다
     */
    private function accessDeniedResponse(string $clientIp): JsonResponse
    {
        // 로그 기록
        \Log::warning('IP Access Denied', [
            'ip' => $clientIp,
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'timestamp' => now()
        ]);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'ACCESS_DENIED',
                'message' => '접근이 거부되었습니다. 허용되지 않은 IP 주소입니다.',
                'details' => [
                    'ip' => $clientIp,
                    'timestamp' => now()->toISOString()
                ]
            ]
        ], 403);
    }
}