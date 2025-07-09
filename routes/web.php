<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// API 전용 서비스이므로 웹 라우트는 최소화
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'version' => config('app.version', '1.0.0'),
        'description' => 'Laravel Blog API Service',
        'documentation' => '/api/documentation',
        'api_version' => 'v1',
        'endpoints' => [
            'health' => '/api/v1/system/health',
            'auth' => '/api/v1/auth/*',
            'docs' => '/api/documentation'
        ]
    ]);
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'services' => [
            'database' => 'connected',
            'cache' => 'available'
        ]
    ]);
});

// API Documentation redirect
Route::get('/docs', function () {
    return redirect('/api/documentation');
});

Route::get('/api/documentation', function () {
    return response()->json([
        'message' => 'API documentation will be available here',
        'swagger_ui' => 'Coming soon',
        'endpoints' => url('/api/v1/system/info')
    ]);
});