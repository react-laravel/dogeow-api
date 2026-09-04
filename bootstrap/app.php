<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\WebSocketAuthMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\SanctumServiceProvider;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: null, // 由 BroadcastServiceProvider 负责 channels 与 auth:sanctum 路由
        health: '/up',
    )
    ->withProviders([
        SanctumServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        // CDN / 反向代理终止 TLS 后，需信任 X-Forwarded-* 才能正确生成 https 签名 URL
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->alias([
            'websocket.auth' => WebSocketAuthMiddleware::class,
            'admin' => EnsureUserIsAdmin::class,
            'idempotency' => IdempotencyMiddleware::class,
        ]);

        // 排除 broadcasting/auth 端点的 CSRF 验证(使用 Sanctum Bearer token 认证)
        $middleware->validateCsrfTokens(except: [
            'api/broadcasting/auth',
            'broadcasting/auth',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
