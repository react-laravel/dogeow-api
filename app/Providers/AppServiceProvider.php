<?php

namespace App\Providers;

use App\Events\Chat\WebSocketDisconnected;
use App\Listeners\WebSocketDisconnectListener;
use App\Listeners\WebPush\LogWebPushResult;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 注册 WebSocket 断开连接事件监听器
        Event::listen(WebSocketDisconnected::class, WebSocketDisconnectListener::class);

        // Web Push 发送结果日志（诊断用）
        Event::listen(NotificationSent::class, LogWebPushResult::class);
        Event::listen(NotificationFailed::class, LogWebPushResult::class);
    }
}
