<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // API 전용 서비스로 전환
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API 전용 CORS 설정
        $middleware->api([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // 별칭 미들웨어 등록
        $middleware->alias([
            'analytics' => \App\Http\Middleware\AnalyticsMiddleware::class,
            'admin' => \App\Http\Middleware\AdminApiMiddleware::class,
            'author' => \App\Http\Middleware\AuthorApiMiddleware::class,
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'ip.restrict' => \App\Http\Middleware\IpRestrictMiddleware::class,
            'admin.ip.restrict' => \App\Http\Middleware\AdminIpRestrictMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
