<?php

namespace App\Providers;

use App\Listeners\Notifications\BroadcastDatabaseNotification;
use App\Listeners\WebPush\LogWebPushResult;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Events\NotificationSent as LaravelNotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Boost\BoostServiceProvider;
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
        if (class_exists(BoostServiceProvider::class)) {
            $this->app->register(BoostServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = (string) config('app.url', '');
        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }

        // Web Push 发送结果日志(诊断用)
        Event::listen(WebPushNotificationSent::class, LogWebPushResult::class);
        Event::listen(WebPushNotificationFailed::class, LogWebPushResult::class);

        // 数据库通知写入后，广播给用户私有频道，供前端实时刷新未读通知
        Event::listen(LaravelNotificationSent::class, BroadcastDatabaseNotification::class);

        // 使用自定义通知模型(支持 UUID)
        Relation::morphMap([
            'notifications' => Notification::class,
        ]);
    }
}
