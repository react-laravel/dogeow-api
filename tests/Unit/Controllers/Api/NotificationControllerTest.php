<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\NotificationController;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    protected NotificationController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new NotificationController;
    }

    public function test_unread_returns_notification_list(): void
    {
        // TODO: Implement test
    }

    public function test_unread_includes_count(): void
    {
        // TODO: Implement test
    }

    public function test_unread_returns_latest_50_notifications(): void
    {
        // TODO: Implement test
    }

    public function test_unread_sends_summary_push_when_conditions_met(): void
    {
        // TODO: Implement test
    }

    public function test_unread_does_not_send_push_when_no_unread(): void
    {
        // TODO: Implement test
    }

    public function test_unread_does_not_send_push_during_cooldown(): void
    {
        // TODO: Implement test
    }

    public function test_unread_does_not_send_push_when_no_subscriptions(): void
    {
        // TODO: Implement test
    }

    public function test_mark_as_read_marks_notification_as_read(): void
    {
        // TODO: Implement test
    }

    public function test_mark_as_read_returns_success(): void
    {
        // TODO: Implement test
    }

    public function test_mark_all_as_read_marks_all_notifications_as_read(): void
    {
        // TODO: Implement test
    }

    public function test_mark_all_as_read_returns_success(): void
    {
        // TODO: Implement test
    }
}
