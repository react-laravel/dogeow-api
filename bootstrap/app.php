<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: null, // 由 BroadcastServiceProvider 负责 channels 与 auth:sanctum 路由
        health: '/up',
    )
    ->withProviders([
        Laravel\Sanctum\SanctumServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'websocket.auth' => \App\Http\Middleware\WebSocketAuthMiddleware::class,
            'combat.rate' => \App\Http\Middleware\CombatRateLimit::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
