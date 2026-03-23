<?php

namespace Tests\Unit\Notifications;

use App\Notifications\WebPushSummaryNotification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class WebPushSummaryNotificationTest extends TestCase
{
    public function test_notification_can_be_instantiated()
    {
        $notification = new WebPushSummaryNotification(
            unreadCount: 5,
            url: '/chat'
        );

        $this->assertInstanceOf(WebPushSummaryNotification::class, $notification);
        $this->assertEquals(5, $notification->unreadCount);
        $this->assertEquals('/chat', $notification->url);
    }

    public function test_notification_can_be_created_with_default_url()
    {
        $notification = new WebPushSummaryNotification(
            unreadCount: 10
        );

        $this->assertEquals(10, $notification->unreadCount);
        $this->assertEquals('/chat', $notification->url);
    }

    public function test_via_returns_only_web_push_channel()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 1);

        $channels = $notification->via(new \App\Models\User);

        $this->assertCount(1, $channels);
        $this->assertContains(WebPushChannel::class, $channels);
    }

    public function test_via_does_not_include_database_channel()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 1);

        $channels = $notification->via(new \App\Models\User);

        $this->assertNotContains(\Illuminate\Notifications\Channels\DatabaseChannel::class, $channels);
    }

    public function test_to_web_push_returns_web_push_message()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 5);

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $this->assertInstanceOf(\NotificationChannels\WebPush\WebPushMessage::class, $message);
    }

    public function test_to_web_push_title_for_single_unread()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 1);

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $title = $message->toArray()['title'];
        $this->assertStringContainsString('1 条未读消息', $title);
    }

    public function test_to_web_push_title_for_multiple_unread()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 5);

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $title = $message->toArray()['title'];
        $this->assertStringContainsString('5 条未读消息', $title);
    }

    public function test_to_web_push_title_for_zero_unread()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 0);

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $title = $message->toArray()['title'];
        $this->assertStringContainsString('0 条未读消息', $title);
    }

    public function test_to_web_push_body_is_click_prompt()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 1);

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $body = $message->toArray()['body'];
        $this->assertEquals('点击查看', $body);
    }

    public function test_to_web_push_includes_custom_url()
    {
        $notification = new WebPushSummaryNotification(
            unreadCount: 1,
            url: '/custom-path'
        );

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $data = $message->toArray()['data'];
        $this->assertEquals('/custom-path', $data['url']);
    }

    public function test_to_web_push_has_icon()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 1);

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $icon = $message->toArray()['icon'];
        $this->assertEquals('/480.png', $icon);
    }

    public function test_to_web_push_has_badge()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 1);

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $badge = $message->toArray()['badge'];
        $this->assertEquals('/80.png', $badge);
    }

    public function test_to_web_push_has_short_ttl()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 1);

        $message = $notification->toWebPush(new \App\Models\User, new \Illuminate\Notifications\Notification);

        $options = $message->getOptions();
        $this->assertArrayHasKey('TTL', $options);
        $this->assertEquals(300, $options['TTL']);
    }

    public function test_notification_implements_should_queue()
    {
        $notification = new WebPushSummaryNotification(unreadCount: 1);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }
}
