<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\SlugController;
use App\Http\Controllers\Api\SeoController;
use App\Http\Controllers\Api\AttachmentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API 버전 관리
Route::prefix('v1')->middleware(['throttle:60,1'])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Routes (인증 라우트)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        // 인증이 필요하지 않은 라우트
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        
        // 인증이 필요한 라우트
        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/me', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'changePassword']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Public Content Routes (공개 컨텐츠 라우트)
    |--------------------------------------------------------------------------
    */
    
    // 포스트 관련 라우트
    Route::prefix('posts')->group(function () {
        Route::get('/', [PostController::class, 'index']);
        Route::get('/{slug}', [PostController::class, 'show']);
        Route::get('/{id}/related', [PostController::class, 'related']);
        
        // 첨부파일 라우트
        Route::get('/{post}/attachments', [AttachmentController::class, 'index']);
        
        // 인증이 필요한 라우트
        Route::middleware(['auth:api', 'author'])->group(function () {
            Route::post('/', [PostController::class, 'store']);
            Route::post('/generate-slug', [PostController::class, 'generateSlug']);
            Route::put('/{post}', [PostController::class, 'update']);
            Route::delete('/{post}', [PostController::class, 'destroy']);
            Route::post('/{post}/publish', [PostController::class, 'publish']);
            Route::post('/{post}/unpublish', [PostController::class, 'unpublish']);
            
            // 첨부파일 관리
            Route::post('/{post}/attachments', [AttachmentController::class, 'store']);
        });
    });
    
    // 미디어 관리 라우트
    Route::prefix('media')->middleware(['auth:api'])->group(function () {
        Route::post('/upload', [MediaController::class, 'upload']);
        Route::post('/content-image', [MediaController::class, 'uploadContentImage']);
        Route::post('/og-image', [MediaController::class, 'uploadOgImage']);
        Route::post('/resize', [MediaController::class, 'resizeImage']);
        Route::get('/', [MediaController::class, 'index']);
        Route::delete('/{id}', [MediaController::class, 'destroy']);
        
        // 관리자만 접근 가능한 통계
        Route::middleware('admin')->group(function () {
            Route::get('/stats', [MediaController::class, 'stats']);
        });
    });
    
    // 태그 관련 라우트
    Route::prefix('tags')->group(function () {
        Route::get('/', [TagController::class, 'index']);
        Route::get('/cloud', [TagController::class, 'cloud']);
        Route::get('/{slug}', [TagController::class, 'show']);
        Route::get('/{slug}/posts', [TagController::class, 'posts']);
        
        // 관리자만 접근 가능한 라우트
        Route::middleware(['auth:api', 'admin'])->group(function () {
            Route::post('/', [TagController::class, 'store']);
            Route::put('/{tag}', [TagController::class, 'update']);
            Route::delete('/{tag}', [TagController::class, 'destroy']);
        });
    });
    
    // 카테고리 관련 라우트
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{slug}', [CategoryController::class, 'show']);
        Route::get('/{slug}/posts', [CategoryController::class, 'posts']);
        Route::get('/{slug}/children', [CategoryController::class, 'children']);
        
        // 관리자만 접근 가능한 라우트
        Route::middleware(['auth:api', 'admin'])->group(function () {
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{category}', [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
        });
    });
    
    // 댓글 관련 라우트
    Route::prefix('posts/{post}/comments')->group(function () {
        Route::get('/', [CommentController::class, 'index']);
        Route::post('/', [CommentController::class, 'store']);
    });
    
    Route::prefix('comments')->group(function () {
        Route::get('/{comment}', [CommentController::class, 'show']);
        Route::put('/{comment}', [CommentController::class, 'update']);
        Route::delete('/{comment}', [CommentController::class, 'destroy']);
        
        // 관리자 전용 라우트
        Route::middleware(['auth:api', 'admin'])->group(function () {
            Route::post('/{comment}/approve', [CommentController::class, 'approve']);
            Route::post('/{comment}/spam', [CommentController::class, 'spam']);
        });
    });
    
    // 페이지 관련 라우트
    Route::prefix('pages')->group(function () {
        Route::get('/', [PageController::class, 'index']);
        Route::get('/menu', [PageController::class, 'menu']);
        Route::get('/{slug}', [PageController::class, 'show']);
        
        // 관리자만 접근 가능한 라우트
        Route::middleware(['auth:api', 'admin'])->group(function () {
            Route::post('/', [PageController::class, 'store']);
            Route::put('/{page}', [PageController::class, 'update']);
            Route::delete('/{page}', [PageController::class, 'destroy']);
        });
    });
    
    // 검색 관련 라우트
    Route::prefix('search')->group(function () {
        Route::get('/', [SearchController::class, 'index']);
        Route::get('/posts', [SearchController::class, 'posts']);
        Route::get('/pages', [SearchController::class, 'pages']);
        Route::get('/autocomplete', [SearchController::class, 'autocomplete']);
        Route::get('/popular', [SearchController::class, 'popular']);
        Route::get('/related', [SearchController::class, 'related']);
        Route::get('/suggestions', [SearchController::class, 'suggestions']);
    });
    
    // 슬러그 생성 관련 라우트
    Route::prefix('slugs')->group(function () {
        Route::post('/generate', [SlugController::class, 'generate']);
        Route::post('/validate', [SlugController::class, 'validate']);
        
        // 관리자 전용 라우트
        Route::middleware(['auth:api', 'admin'])->group(function () {
            Route::post('/batch-generate', [SlugController::class, 'batchGenerate']);
        });
    });
    
    // SEO 관련 라우트
    Route::prefix('seo')->group(function () {
        Route::get('/preview/{slug}', [SeoController::class, 'previewSeo']);
        Route::get('/sitemap', [SeoController::class, 'getSitemapData']);
        Route::post('/analyze', [SeoController::class, 'analyzeSeo']);
        
        // 인증이 필요한 라우트
        Route::middleware(['auth:api'])->group(function () {
            Route::get('/post/{post}', [SeoController::class, 'getPostSeo']);
            Route::post('/post/{post}', [SeoController::class, 'updatePostSeo']);
        });
    });
    
    // 첨부파일 관련 라우트
    Route::prefix('attachments')->group(function () {
        Route::get('/{attachment}', [AttachmentController::class, 'show']);
        Route::get('/{attachment}/download', [AttachmentController::class, 'download']);
        
        // 인증이 필요한 라우트
        Route::middleware(['auth:api'])->group(function () {
            Route::put('/{attachment}', [AttachmentController::class, 'update']);
            Route::delete('/{attachment}', [AttachmentController::class, 'destroy']);
        });
    });
    
    // 관리자용 관리 라우트
    Route::prefix('admin')->middleware(['auth:api', 'admin', 'admin.ip.restrict'])->group(function () {
        Route::get('/comments', [CommentController::class, 'adminIndex']);
        Route::get('/pages', [PageController::class, 'adminIndex']);
        
        // 사용자 관리
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/stats', [UserController::class, 'stats']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::put('/users/{user}/role', [UserController::class, 'updateRole']);
        Route::post('/users/{user}/verify-email', [UserController::class, 'verifyEmail']);
        
        // 분석 및 통계
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
            Route::get('/posts', [AnalyticsController::class, 'posts']);
            Route::get('/users', [AnalyticsController::class, 'users']);
            Route::get('/traffic', [AnalyticsController::class, 'traffic']);
            Route::get('/popular-content', [AnalyticsController::class, 'popularContent']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | System Routes (시스템 라우트)
    |--------------------------------------------------------------------------
    */
    Route::prefix('system')->group(function () {
        Route::get('/health', function () {
            return response()->json([
                'status' => 'ok',
                'timestamp' => now(),
                'version' => config('app.version', '1.0.0')
            ]);
        });
        
        Route::get('/info', function () {
            return response()->json([
                'app_name' => config('app.name'),
                'version' => config('app.version', '1.0.0'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version()
            ]);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Fallback Routes (대체 라우트)
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'error' => [
            'code' => 'ROUTE_NOT_FOUND',
            'message' => '요청하신 API 엔드포인트를 찾을 수 없습니다.',
            'details' => [
                'available_versions' => ['v1'],
                'documentation' => url('/api/documentation')
            ]
        ]
    ], 404);
});