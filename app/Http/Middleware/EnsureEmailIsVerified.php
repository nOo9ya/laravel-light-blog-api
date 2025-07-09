<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as BaseEnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified extends BaseEnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if (! $request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
            ! $request->user()->hasVerifiedEmail())) {
            
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => '이메일 인증이 필요합니다.',
                    'verification_required' => true
                ], 409);
            }

            return redirect()->guest($redirectToRoute ?: route('verification.notice'));
        }

        return $next($request);
    }
}