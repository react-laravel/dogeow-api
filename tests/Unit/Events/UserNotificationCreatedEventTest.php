<?php

namespace Tests\Unit\Events;

use App\Events\UserNotificationCreated;
use Tests\TestCase;

class UserNotificationCreatedEventTest extends TestCase
{
    public function test_event_can_be_instantiated(): void
    {
        $event = new UserNotificationCreated(
            1,
            'test-id',
            'test.type',
            ['title' => 'Test'],
            '2024-01-01 00:00:00',
            5
        );

        $this->assertInstanceOf(UserNotificationCreated::class, $event);
        $this->assertEquals(1, $event->userId);
    }

    public function test_broadcast_on_returns_user_channel(): void
    {
        $event = new UserNotificationCreated(
            123,
            'test-id',
            'test.type',
            ['title' => 'Test'],
            '2024-01-01 00:00:00',
            5
        );
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('user.123.notifications', $channels[0]->name);
    }

    public function test_broadcast_as_returns_notification_created(): void
    {
        $event = new UserNotificationCreated(
            1,
            'test-id',
            'test.type',
            ['title' => 'Test'],
            '2024-01-01 00:00:00',
            5
        );

        $this->assertEquals('notification.created', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_correct_data(): void
    {
        $event = new UserNotificationCreated(
            1,
            'notif-123',
            'test.type',
            ['title' => 'Hello', 'body' => 'World'],
            '2024-01-01 00:00:00',
            10
        );

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('notification', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertEquals('notif-123', $data['notification']['id']);
        $this->assertEquals(10, $data['count']);
    }
}
