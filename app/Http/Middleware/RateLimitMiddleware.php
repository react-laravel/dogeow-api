<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * 处理传入的请求。
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        $key = $this->resolveRequestSignature($request);

        $max = (int) $maxAttempts;
        $decay = (int) $decayMinutes * 60;

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => '请求过于频繁，请稍后再试',
                'retry_after' => $retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $max,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($key, $decay);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // 设置限流相关头部信息
        $remaining = RateLimiter::remaining($key, $max);

        $response->headers->set('X-RateLimit-Limit', (string) $max);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', (string) (time() + RateLimiter::availableIn($key)));

        return $response;
    }

    /**
     * 根据用户或 IP 获取限流标识 key。
     */
    protected function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->user()) {
            return 'rate_limit:user:' . $user->getAuthIdentifier();
        }

        return 'rate_limit:ip:' . $request->ip();
    }
}
