<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler
{
    /**
     * 处理 API 异常
     */
    public static function handle(Throwable $exception, Request $request): ?JsonResponse
    {
        // 只处理 API 请求
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $exception instanceof GameException => self::handleGameException($exception),
            $exception instanceof ValidationException => self::handleValidationException($exception),
            $exception instanceof ModelNotFoundException => self::handleModelNotFoundException($exception),
            $exception instanceof NotFoundHttpException => self::handleNotFoundHttpException(),
            $exception instanceof AuthenticationException => self::handleAuthenticationException($exception),
            $exception instanceof HttpException => self::handleHttpException($exception),
            default => self::handleGenericException($exception),
        };
    }

    /**
     * 处理验证异常
     */
    private static function handleValidationException(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $exception->errors(),
        ], 422);
    }

    /**
     * 处理游戏业务异常
     */
    private static function handleGameException(GameException $exception): JsonResponse
    {
        return response()->json($exception->toResponseArray(), 400);
    }

    /**
     * 处理模型未找到异常
     */
    private static function handleModelNotFoundException(ModelNotFoundException $exception): JsonResponse
    {
        $model = class_basename($exception->getModel());

        return response()->json([
            'success' => false,
            'message' => __("{$model} not found"),
        ], 404);
    }

    /**
     * 处理 404 异常
     */
    private static function handleNotFoundHttpException(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Resource not found'),
        ], 404);
    }

    /**
     * 处理认证异常
     */
    private static function handleAuthenticationException(AuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Unauthenticated'),
        ], 401);
    }

    /**
     * 处理 HTTP 异常
     */
    private static function handleHttpException(HttpException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage() ?: __('HTTP Error'),
        ], $exception->getStatusCode());
    }

    /**
     * 处理通用异常
     */
    private static function handleGenericException(Throwable $exception): JsonResponse
    {
        $isProd = app()->environment('production');
        $message = $isProd ? __('Internal server error') : $exception->getMessage();

        $data = [
            'success' => false,
            'message' => $message,
        ];

        // 在非生产环境中添加调试信息
        if (! $isProd) {
            $data['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())
                    ->map(fn ($item) => Arr::only($item, ['file', 'line', 'function', 'class']))
                    ->all(),
            ];
        }

        return response()->json($data, 500);
    }
}
