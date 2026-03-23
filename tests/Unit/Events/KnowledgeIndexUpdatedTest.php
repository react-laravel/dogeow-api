<?php

namespace Tests\Unit\Events;

use App\Events\KnowledgeIndexUpdated;
use Tests\TestCase;

class KnowledgeIndexUpdatedTest extends TestCase
{
    public function test_event_can_be_instantiated(): void
    {
        $updatedAt = '2024-01-15T10:30:00+08:00';
        $event = new KnowledgeIndexUpdated($updatedAt);

        $this->assertInstanceOf(KnowledgeIndexUpdated::class, $event);
        $this->assertSame($updatedAt, $event->updatedAt);
    }

    public function test_broadcast_on_returns_knowledge_index_channel(): void
    {
        $event = new KnowledgeIndexUpdated(now()->toIso8601String());
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('knowledge-index', $channels[0]->name);
    }

    public function test_broadcast_as_returns_knowledge_index_updated(): void
    {
        $event = new KnowledgeIndexUpdated('2024-01-01T00:00:00Z');

        $this->assertSame('knowledge.index.updated', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_updated_at(): void
    {
        $updatedAt = '2024-06-01T12:00:00+08:00';
        $event = new KnowledgeIndexUpdated($updatedAt);

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('updated_at', $data);
        $this->assertSame($updatedAt, $data['updated_at']);
    }
}
