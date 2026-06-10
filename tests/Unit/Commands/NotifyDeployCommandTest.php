<?php

namespace Tests\Unit\Commands;

use App\Models\User;
use App\Notifications\WebPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyDeployCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_when_no_user_ids_configured(): void
    {
        Notification::fake();
        config(['services.deploy_notify.user_ids' => []]);

        $this->artisan('notify:deploy dogeow-api')
            ->expectsOutputToContain('未配置 DEPLOY_NOTIFY_USER_IDS')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_sends_deploy_notification_to_configured_users(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        config(['services.deploy_notify.user_ids' => [$user->id]]);

        $this->artisan('notify:deploy dogeow-api')
            ->expectsOutputToContain("已向用户 {$user->id} 发送部署通知")
            ->assertSuccessful();

        Notification::assertSentTo(
            $user,
            WebPushNotification::class,
            function (WebPushNotification $notification): bool {
                return $notification->title === '后端部署完成'
                    && str_contains($notification->body, 'dogeow-api')
                    && $notification->tag === 'deploy-dogeow-api';
            }
        );
    }

    public function test_uses_custom_title_and_body_options(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        config(['services.deploy_notify.user_ids' => [$user->id]]);

        $this->artisan('notify:deploy dogeow --title=自定义标题 --body=自定义正文 --url=/game/rpg')
            ->assertSuccessful();

        Notification::assertSentTo(
            $user,
            WebPushNotification::class,
            function (WebPushNotification $notification): bool {
                return $notification->title === '自定义标题'
                    && $notification->body === '自定义正文'
                    && $notification->url === '/game/rpg'
                    && $notification->tag === 'deploy-dogeow';
            }
        );
    }
}
