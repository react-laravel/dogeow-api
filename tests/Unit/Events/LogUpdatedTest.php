<?php

namespace Tests\Unit\Events;

use App\Events\LogUpdated;
use Tests\TestCase;

class LogUpdatedTest extends TestCase
{
    public function test_event_can_be_instantiated(): void
    {
        $event = new LogUpdated('2024-01-15', 1024);

        $this->assertInstanceOf(LogUpdated::class, $event);
        $this->assertSame('2024-01-15', $event->date);
        $this->assertSame(1024, $event->size);
    }

    public function test_broadcast_on_returns_log_updates_channel(): void
    {
        $event = new LogUpdated('2024-01-01', 0);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('log-updates', $channels[0]->name);
    }

    public function test_broadcast_as_returns_log_updated(): void
    {
        $event = new LogUpdated('', 0);

        $this->assertSame('log.updated', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_date_and_size(): void
    {
        $event = new LogUpdated('2024-06-15', 2048);

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('date', $data);
        $this->assertArrayHasKey('size', $data);
        $this->assertSame('2024-06-15', $data['date']);
        $this->assertSame(2048, $data['size']);
    }
}
