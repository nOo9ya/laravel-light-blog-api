<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\ErrorNotificationService;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // 에러 알림 전송
            $this->sendErrorNotification($e);
        });
    }

    /**
     * 에러 알림 전송
     */
    protected function sendErrorNotification(Throwable $exception): void
    {
        try {
            // 알림을 보내지 않을 예외 타입들
            $skipNotificationFor = [
                \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
                \Illuminate\Auth\AuthenticationException::class,
                \Illuminate\Validation\ValidationException::class,
                \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
                \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
            ];

            // 알림을 보내지 않을 예외인지 확인
            foreach ($skipNotificationFor as $exceptionType) {
                if ($exception instanceof $exceptionType) {
                    return;
                }
            }

            // 프로덕션 환경에서만 알림 전송
            if (app()->environment('production')) {
                $notificationService = app(ErrorNotificationService::class);
                
                $level = $this->getErrorLevel($exception);
                $message = $exception->getMessage();
                $file = $exception->getFile();
                $line = $exception->getLine();
                $stackTrace = $exception->getTraceAsString();

                $notificationService->sendErrorNotification($level, $message, $file, $line, $stackTrace);
            }
        } catch (Throwable $e) {
            // 알림 전송 중 에러가 발생해도 원본 예외 처리에는 영향을 주지 않음
            logger()->error('Failed to send error notification: ' . $e->getMessage());
        }
    }

    /**
     * 예외 타입에 따른 에러 레벨 결정
     */
    protected function getErrorLevel(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \Error => 'critical',
            $exception instanceof \ErrorException => 'error',
            $exception instanceof \RuntimeException => 'error',
            $exception instanceof \LogicException => 'error',
            default => 'error'
        };
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception): Response
    {
        return parent::render($request, $exception);
    }
}