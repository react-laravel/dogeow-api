## 技术栈

-   Laravel 12
    - 官方库
        -   Laravel Octane (性能优化)
        -   Laravel Horizon (队列监控)
        -   Laravel Telescope (调试工具)
        -   Laravel Pint (代码格式化)
        -   Reverb
        -   Sanctum
    -   Spatie 库
        -   laravel-query-builder
        -   laravel-permission
        -   laravel-activitylog
        -   laravel-backup
        -   laravel-medialibrary
        -   Scout
        -   intervention/image
- 服务器
    -   PHP 8.4
    -   MySQL 8
    -   Redis 7
    -   Nginx

## 需要的扩展

-   php8.2-imagick

---

## Web Push 推送：如何给用户发消息

### 前置准备

1. 生成 VAPID 密钥（仅需一次）：`php artisan webpush:vapid`
2. Safari/iOS 需在 `.env` 设置 `VAPID_SUBJECT=https://你的域名` 或 `mailto:admin@example.com`
3. 执行迁移：`php artisan migrate`（会创建 `push_subscriptions` 表）
4. 推送走队列，需运行：`php artisan queue:work`

### 发送一条推送

```php
use App\Models\User;
use App\Notifications\WebPushNotification;

$user = User::find($userId);
$user->notify(new WebPushNotification(
    title: '通知标题',
    body: '正文内容',
    url: '/chat',      // 可选，点击打开的链接，默认 '/'
    icon: null,        // 可选，默认 /480.png
    tag: 'my-tag'      // 可选，同 tag 只保留一条
));
```

- **title** 必填，**body** / **url** / **icon** / **tag** 可选。
- 用户需已登录并授权过浏览器通知，前端会自动上报订阅到 `POST /api/user/push-subscription`。

### API 摘要

| 说明 | 接口 |
|------|------|
| 获取 VAPID 公钥 | `GET /api/webpush/vapid`（公开） |
| 保存推送订阅 | `POST /api/user/push-subscription`（需登录） |
| 删除推送订阅 | `DELETE /api/user/push-subscription`，body `{"endpoint":"..."}`（需登录） |
