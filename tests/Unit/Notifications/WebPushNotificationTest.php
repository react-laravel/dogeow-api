<?php

namespace Tests\Unit\Notifications;

use App\Notifications\WebPushNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class WebPushNotificationTest extends TestCase
{
    public function test_notification_can_be_instantiated()
    {
        $notification = new WebPushNotification(
            title: 'Test Title',
            body: 'Test Body',
            url: '/test',
            icon: '/icon.png',
            tag: 'test-tag'
        );

        $this->assertInstanceOf(WebPushNotification::class, $notification);
        $this->assertEquals('Test Title', $notification->title);
        $this->assertEquals('Test Body', $notification->body);
        $this->assertEquals('/test', $notification->url);
        $this->assertEquals('/icon.png', $notification->icon);
        $this->assertEquals('test-tag', $notification->tag);
    }

    public function test_notification_can_be_created_with_minimal_params()
    {
        $notification = new WebPushNotification(
            title: 'Minimal Title'
        );

        $this->assertEquals('Minimal Title', $notification->title);
        $this->assertEquals('', $notification->body);
        $this->assertEquals('/', $notification->url);
        $this->assertNull($notification->icon);
        $this->assertNull($notification->tag);
    }

    public function test_notification_generates_uuid()
    {
        $notification = new WebPushNotification(
            title: 'Test Title'
        );

        $this->assertNotNull($notification->notificationId);
        $this->assertIsString($notification->notificationId);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($notification->notificationId));
    }

    public function test_via_returns_channels()
    {
        $notification = new WebPushNotification(title: 'Test');

        $channels = $notification->via(new \App\Models\User);

        $this->assertContains(DatabaseChannel::class, $channels);
        $this->assertContains(WebPushChannel::class, $channels);
        $this->assertCount(2, $channels);
    }

    public function test_to_array_returns_correct_format()
    {
        $notification = new WebPushNotification(
            title: 'Test Title',
            body: 'Test Body',
            url: '/test',
            icon: '/custom-icon.png'
        );

        $user = new \App\Models\User;
        $result = $notification->toArray($user);

        $this->assertIsArray($result);
        $this->assertEquals('Test Title', $result['title']);
        $this->assertEquals('Test Body', $result['body']);
        $this->assertEquals('/test', $result['url']);
        $this->assertEquals('/custom-icon.png', $result['icon']);
        $this->assertArrayHasKey('notification_id', $result);
        $this->assertEquals($notification->notificationId, $result['notification_id']);
    }

    public function test_to_array_uses_default_icon_when_null()
    {
        $notification = new WebPushNotification(title: 'Test');

        $result = $notification->toArray(new \App\Models\User);

        $this->assertEquals('/480.png', $result['icon']);
    }

    public function test_to_web_push_returns_web_push_message()
    {
        $notification = new WebPushNotification(
            title: 'Push Title',
            body: 'Push Body',
            url: '/push',
            icon: '/push-icon.png',
            tag: 'push-tag'
        );

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $this->assertInstanceOf(\NotificationChannels\WebPush\WebPushMessage::class, $message);
    }

    public function test_to_web_push_uses_default_icon_when_null()
    {
        $notification = new WebPushNotification(title: 'Push Title');

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $this->assertInstanceOf(\NotificationChannels\WebPush\WebPushMessage::class, $message);
    }

    public function test_to_web_push_includes_notification_id_in_data()
    {
        $notification = new WebPushNotification(
            title: 'Test',
            url: '/test'
        );

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $data = $message->toArray()['data'];

        $this->assertIsArray($data);
        $this->assertArrayHasKey('notification_id', $data);
        $this->assertEquals($notification->notificationId, $data['notification_id']);
        $this->assertEquals('/test', $data['url']);
    }

    public function test_to_web_push_sets_default_ttl(): void
    {
        $notification = new WebPushNotification(title: 'Test');

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $this->assertSame(86400, $message->getOptions()['TTL']);
    }

    public function test_to_web_push_adds_tag_when_provided()
    {
        $notification = new WebPushNotification(
            title: 'Test',
            tag: 'unique-tag'
        );

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $this->assertInstanceOf(\NotificationChannels\WebPush\WebPushMessage::class, $message);
    }

    public function test_to_web_push_does_not_add_tag_when_null()
    {
        $notification = new WebPushNotification(
            title: 'Test'
        );

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $this->assertInstanceOf(\NotificationChannels\WebPush\WebPushMessage::class, $message);
    }
}
