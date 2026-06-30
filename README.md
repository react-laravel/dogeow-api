# dogeow-api

## 技术栈

- Laravel 13
  - 官方库
    - Laravel Octane (性能优化)
    - Laravel Horizon (队列监控)
    - Laravel Telescope (调试工具)
    - Laravel Pint (代码格式化)
    - Reverb
    - Sanctum
  - Spatie 库
    - laravel-query-builder
    - laravel-permission
    - laravel-activitylog
    - laravel-medialibrary
    - Scout
    - intervention/image
- 服务器
  - PHP 8.4
  - PostgreSQL 18
  - Redis 7
  - Nginx

## 需要的扩展

- php[版本]-imagick

## 部署

- 生产环境部署使用 Deployer，完整步骤见 [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
- 当前仓库的部署入口是 [deploy.php](deploy.php)，支持 GitHub Actions self-hosted runner 和手动执行 `scripts/ensure-deployer.sh deploy production`
- 首次部署前请先准备好服务器上的 `shared/.env`、`shared/storage`、Nginx `root`、Supervisor/Horizon 配置，细节按 [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) 执行

## 数据初始化

### 固定基础数据

- 首次初始化数据库：`php artisan migrate:fresh --seed`
- 仅填充 RPG 基础定义数据：`php artisan db:seed --class=Database\\Seeders\\Game\\GameSeeder`
- `DatabaseSeeder` 默认会写入管理员、测试用户、词库，以及 RPG 的技能、物品、怪物、地图基础定义

### Factory 随机数据

- 本地联调或测试环境可选执行：`php artisan db:seed --class=Database\\Seeders\\Game\\GameFactorySeeder`
- 该 Seeder 会通过 Factory 生成随机 RPG 技能、物品、怪物、地图定义，不会默认加入 `DatabaseSeeder`
- 测试里也可以直接使用地图工厂自动挂载怪物：`GameMapDefinition::factory()->withMonsters(3)->create()`

---

## Web Push 推送：如何给用户发消息

### 前置准备

1. 生成 VAPID 密钥（仅需一次）：`php artisan webpush:vapid`
2. Safari/iOS 需在 `.env` 设置 `VAPID_SUBJECT=https://你的域名` 或 `mailto:admin@example.com`
3. 执行迁移：`php artisan migrate`（会创建 `push_subscriptions` 和 `notifications` 表）
4. 推送走队列，需运行：`php artisan queue:work`

未读与「打开时补发」：每次发 Web Push 会同时写入 `notifications` 表。用户打开浏览器时前端会请求 `GET /api/notifications/unread`，若有未读且 5 分钟内未发过汇总推送，后端会补发一条「你有 N 条未读消息」。

### 发送一条推送

```php
use App\Models\User;
use App\Notifications\WebPushNotification;

$user = User::find($userId);
$user->notify(new WebPushNotification(
  title: '通知标题',
  body: '正文内容',
  url: '/chat',  // 可选，点击打开的链接，默认 '/'
  icon: null,    // 可选，默认 /480.png
  tag: 'my-tag'  // 可选，同 tag 只保留一条
));
```

### Tinker

```php
$userId = 1;  // 改成你要推送的用户 ID
$user = App\Models\User::find($userId);
$user->notify(new App\Notifications\WebPushNotification(
  title: '通知标题',
  body: '正文内容',
  url: '/chat',
  icon: null,
  tag: 'my-tag'
));
```

### 一行

```php
App\Models\User::find(1)->notify(new App\Notifications\WebPushNotification(title: '测试', body: '来自 Tinker 的推送', url: '/chat'));
```

### 查看推送次数

```php
App\Models\User::find(1)->pushSubscriptions()->count()
```

### 直接推送，不走队列

```php
App\Models\User::find(1)->notifyNow(new App\Notifications\WebPushNotification(title: '测试', body: '来自 Tinker 的推送', url: '/chat'));
```

### php artisan webpush:test 1

- **title** 必填，**body** / **url** / **icon** / **tag** 可选。
- 用户需已登录并授权过浏览器通知，前端会自动上报订阅到 `POST /api/user/push-subscription`。

### API 摘要

| 说明 | 接口 |
| --- | --- |
| 获取 VAPID 公钥 | `GET /api/webpush/vapid`（公开） |
| 保存推送订阅 | `POST /api/user/push-subscription`（需登录） |
| 删除推送订阅 | `DELETE /api/user/push-subscription`，body `{"endpoint":"..."}`（需登录） |
