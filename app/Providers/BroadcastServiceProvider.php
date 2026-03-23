<?php

namespace App\Providers;

use Illuminate\Broadcasting\BroadcastServiceProvider as LaravelBroadcastServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends LaravelBroadcastServiceProvider
{
    public function boot(): void
    {
        require base_path('routes/channels.php');

        // 不使用默认的 Broadcast::routes()，因为它会强制要求认证
        // 我们在 routes/api/broadcast.php 中有自定义的路由，支持公共频道
        // Broadcast::routes(['middleware' => ['web', 'auth:sanctum']]);
    }
}
