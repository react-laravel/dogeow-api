<?php

namespace Tests\Unit\Listeners;

use App\Events\UserNotificationCreated;
use App\Listeners\Notifications\BroadcastDatabaseNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class BroadcastDatabaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_returns_early_when_notifiable_is_not_user(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $listener = new BroadcastDatabaseNotification;
        $event = new NotificationSent(new \stdClass, new \stdClass, 'database');

        $listener->handle($event);

        Event::assertNotDispatched(UserNotificationCreated::class);
    }

    public function test_handle_returns_early_when_channel_is_not_database(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $user = User::factory()->create();
        $listener = new BroadcastDatabaseNotification;
        $event = new NotificationSent($user, new \stdClass, 'mail');

        $listener->handle($event);

        Event::assertNotDispatched(UserNotificationCreated::class);
    }

    public function test_handle_accepts_database_channel_class(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $user = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\Notification',
            'data' => ['title' => 'Test'],
        ]);

        $listener = new BroadcastDatabaseNotification;
        $event = new NotificationSent($user, new \stdClass, DatabaseChannel::class, $notification);

        $listener->handle($event);

        Event::assertDispatched(UserNotificationCreated::class, function (UserNotificationCreated $e): bool {
            return $e->notificationId !== ''
                && $e->type === 'Test\Notification'
                && $e->data === ['title' => 'Test'];
        });
    }

    public function test_handle_returns_early_when_notification_is_read(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $user = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\Notification',
            'data' => ['title' => 'Test'],
            'read_at' => now(),
        ]);

        $listener = new BroadcastDatabaseNotification;
        $event = new NotificationSent($user, new \stdClass, 'database', $notification);

        $listener->handle($event);

        Event::assertNotDispatched(UserNotificationCreated::class);
    }

    public function test_handle_returns_early_when_response_and_fallback_are_not_database_notification(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $user = User::factory()->create();
        $listener = new BroadcastDatabaseNotification;
        $event = new NotificationSent($user, new \stdClass, 'database', new \stdClass);

        $listener->handle($event);

        Event::assertNotDispatched(UserNotificationCreated::class);
    }

    public function test_handle_broadcasts_when_response_is_database_notification(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $user = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\WebPushNotification',
            'data' => ['title' => 'Hello', 'body' => 'World'],
        ]);

        $listener = new BroadcastDatabaseNotification;
        $event = new NotificationSent($user, new \stdClass, 'database', $notification);

        $listener->handle($event);

        Event::assertDispatched(UserNotificationCreated::class, function (UserNotificationCreated $e) use ($user, $notification): bool {
            return $e->userId === $user->id
                && $e->notificationId === (string) $notification->id
                && $e->type === 'App\Notifications\WebPushNotification'
                && $e->data === ['title' => 'Hello', 'body' => 'World']
                && $e->unreadCount === 1;
        });
    }

    public function test_handle_uses_fallback_when_response_is_not_database_notification(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $user = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'FallbackType',
            'data' => ['key' => 'fallback'],
        ]);

        $listener = new BroadcastDatabaseNotification;
        $event = new NotificationSent($user, new \stdClass, 'database', null);

        $listener->handle($event);

        Event::assertDispatched(UserNotificationCreated::class, function (UserNotificationCreated $e) use ($notification): bool {
            return $e->notificationId === (string) $notification->id
                && $e->type === 'FallbackType'
                && $e->data === ['key' => 'fallback'];
        });
    }

    public function test_handle_includes_iso8601_created_at(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $user = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\Notification',
            'data' => [],
        ]);

        $listener = new BroadcastDatabaseNotification;
        $event = new NotificationSent($user, new \stdClass, 'database', $notification);

        $listener->handle($event);

        Event::assertDispatched(UserNotificationCreated::class, function (UserNotificationCreated $e): bool {
            $iso8601 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';

            return (bool) preg_match($iso8601, $e->createdAt);
        });
    }
}
