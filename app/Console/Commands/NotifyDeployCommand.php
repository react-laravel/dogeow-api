<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WebPushNotification;
use Illuminate\Console\Command;

class NotifyDeployCommand extends Command
{
    protected $signature = 'notify:deploy
        {app=dogeow-api : 部署的应用名，如 dogeow 或 dogeow-api}
        {--title= : 通知标题}
        {--body= : 通知正文}
        {--url=/ : 点击跳转 URL}';

    protected $description = '部署完成后向配置的用户发送站内通知与 Web Push';

    public function handle(): int
    {
        $userIds = $this->resolveUserIds();
        if ($userIds === []) {
            $this->warn('未配置 DEPLOY_NOTIFY_USER_IDS，跳过部署通知');

            return self::SUCCESS;
        }

        $app = (string) $this->argument('app');
        $title = (string) ($this->option('title') ?: $this->defaultTitle($app));
        $body = (string) ($this->option('body') ?: "「{$app}」已成功部署到生产环境");
        $url = (string) ($this->option('url') ?: '/');

        $sent = 0;
        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                $this->warn("用户 {$userId} 不存在，跳过");

                continue;
            }

            $user->notify(new WebPushNotification(
                title: $title,
                body: $body,
                url: $url,
                tag: 'deploy-' . $app,
            ));
            $sent++;
            $this->info("已向用户 {$userId} 发送部署通知");
        }

        if ($sent === 0) {
            $this->warn('没有有效的通知接收用户');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserIds(): array
    {
        /** @var array<int, int|string> $configured */
        $configured = config('services.deploy_notify.user_ids', []);

        return array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $configured
        ), static fn (int $id): bool => $id > 0)));
    }

    private function defaultTitle(string $app): string
    {
        return match ($app) {
            'dogeow' => '前端部署完成',
            'dogeow-api' => '后端部署完成',
            default => '部署完成',
        };
    }
}
