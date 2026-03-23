<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WebPushNotification;
use Illuminate\Console\Command;

class WebPushTestCommand extends Command
{
    protected $signature = 'webpush:test {user_id=1 : 用户 ID}';

    protected $description = '向指定用户发送一条测试 Web Push，并提示查看日志中的发送结果';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $user = User::find($userId);

        if (! $user) {
            $this->error("用户 {$userId} 不存在");

            return 1;
        }

        $count = $user->pushSubscriptions()->count();
        if ($count === 0) {
            $this->error("用户 {$userId} 没有任何推送订阅，请先在前端登录并允许通知后刷新页面");

            return 1;
        }

        $this->info("用户 {$userId} 有 {$count} 个订阅，正在发送测试推送…");
        $user->notifyNow(new WebPushNotification(
            title: '测试推送',
            body: '来自 php artisan webpush:test 的测试消息',
            url: '/'
        ));

        $this->info('已发送。请查看 storage/logs/laravel.log 中 "Web Push 发送成功" 或 "Web Push 发送失败" 的日志，确认 status_code 和 response_body。');

        return 0;
    }
}
