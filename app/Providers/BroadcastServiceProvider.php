<?php

namespace App\Providers;

use Illuminate\Broadcasting\BroadcastServiceProvider as LaravelBroadcastServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends LaravelBroadcastServiceProvider
{
    public function boot(): void
    {
        require base_path('routes/channels.php');

        // 使用 auth:sanctum 支持 Bearer token（SPA/API 场景）
        // Laravel 默认 web 中间件不解析 Bearer token，导致私有频道订阅 403
        Broadcast::routes(['middleware' => ['web', 'auth:sanctum']]);
    }
}
