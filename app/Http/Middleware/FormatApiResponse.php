<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormatApiResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 仅格式化 API 路径下的 JSON 响应
        if (
            ! $request->is('api/*') ||
            ! $response instanceof JsonResponse
        ) {
            return $response;
        }

        $data = $response->getData(true);
        $statusCode = $response->getStatusCode();

        // 非数组数据(null、标量等)直接包装
        if (! is_array($data)) {
            $success = $statusCode >= 200 && $statusCode < 300;
            $formatted = [
                'success' => $success,
                'message' => $this->getDefaultMessage($statusCode),
            ];

            if ($data !== null) {
                $formatted[$success ? 'data' : 'errors'] = $data;
            }

            return response()->json($formatted, $statusCode);
        }

        // 已含有标准格式字段，跳过格式化
        if ($this->isStandardResponse($data)) {
            return $response;
        }

        return response()->json(
            $this->formatResponse($data, $statusCode),
            $statusCode
        );
    }

    /**
     * 标准化响应结构
     */
    private function formatResponse(array $data, int $statusCode): array
    {
        $success = $statusCode >= 200 && $statusCode < 300;
        $payload = $this->resolvePayload($data, $success);

        $response = [
            'success' => $success,
            'message' => $this->resolveMessage($data, $statusCode),
        ];

        if ($payload !== null) {
            $response[$success ? 'data' : 'errors'] = $payload;
        }

        return $response;
    }

    private function isStandardResponse(array $data): bool
    {
        return array_key_exists('success', $data) && array_key_exists('message', $data);
    }

    private function resolveMessage(array $data, int $statusCode): string
    {
        if (isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
            return $data['message'];
        }

        if (isset($data['error']) && is_string($data['error']) && $data['error'] !== '') {
            return $data['error'];
        }

        return $this->getDefaultMessage($statusCode);
    }

    private function resolvePayload(array $data, bool $success): mixed
    {
        if ($success) {
            if (count($data) === 0) {
                return null;
            }

            if (count($data) === 1 && array_key_exists('data', $data)) {
                return $data['data'];
            }

            return $data;
        }

        if (array_key_exists('errors', $data)) {
            return $data['errors'];
        }

        $payload = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['message', 'error'], true)) {
                continue;
            }

            $payload[$key] = $value;
        }

        return count($payload) > 0 ? $payload : null;
    }

    /**
     * 获取响应默认消息
     */
    private function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'Success',
            201 => 'Created successfully',
            204 => 'No content',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            422 => 'Validation failed',
            429 => 'Too many requests',
            500 => 'Internal server error',
            default => 'Request processed',
        };
    }
}
