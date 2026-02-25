<?php

namespace App\Providers;

use App\Events\Chat\WebSocketDisconnected;
use App\Listeners\Notifications\BroadcastDatabaseNotification;
use App\Listeners\WebSocketDisconnectListener;
use App\Listeners\WebPush\LogWebPushResult;
use Illuminate\Notifications\Events\NotificationSent as LaravelNotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\Events\NotificationFailed as WebPushNotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent as WebPushNotificationSent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Laravel Boost 仅作为 dev 依赖，生产部署用 --no-dev 不会安装；避免生产从旧缓存加载导致 Class not found
        if (class_exists(\Laravel\Boost\BoostServiceProvider::class)) {
            $this->app->register(\Laravel\Boost\BoostServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 注册 WebSocket 断开连接事件监听器
        Event::listen(WebSocketDisconnected::class, WebSocketDisconnectListener::class);

        // Web Push 发送结果日志（诊断用）
        Event::listen(WebPushNotificationSent::class, LogWebPushResult::class);
        Event::listen(WebPushNotificationFailed::class, LogWebPushResult::class);

        // 数据库通知写入后，广播给用户私有频道，供前端实时刷新未读通知
        Event::listen(LaravelNotificationSent::class, BroadcastDatabaseNotification::class);
    }
}
