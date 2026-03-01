<?php

namespace Tests\Unit\Services;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatRoom;
use App\Models\User;
use App\Services\Chat\ChatPaginationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatPaginationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ChatPaginationService $paginationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paginationService = new ChatPaginationService;
    }

    #[Test]
    public function it_gets_messages_with_cursor_pagination()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create messages with different timestamps
        $messages = ChatMessage::factory()->count(10)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $result = $this->paginationService->getMessagesCursor($room->id);

        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('next_cursor', $result);
        $this->assertArrayHasKey('prev_cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertCount(10, $result['messages']);
    }

    #[Test]
    public function it_gets_messages_with_limit()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create more messages than limit
        ChatMessage::factory()->count(15)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $result = $this->paginationService->getMessagesCursor($room->id, null, 5);

        $this->assertCount(5, $result['messages']);
        $this->assertTrue($result['has_more']);
    }

    #[Test]
    public function it_gets_messages_before_cursor()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create messages
        $messages = ChatMessage::factory()->count(10)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        // Get first page
        $firstResult = $this->paginationService->getMessagesCursor($room->id, null, 5);

        // Get second page using cursor
        $secondResult = $this->paginationService->getMessagesCursor(
            $room->id,
            $firstResult['next_cursor'],
            5,
            'before'
        );

        $this->assertCount(5, $secondResult['messages']);
        $this->assertNotEquals($firstResult['messages']->first()->id, $secondResult['messages']->first()->id);
    }

    #[Test]
    public function it_gets_messages_after_cursor()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create messages
        $messages = ChatMessage::factory()->count(10)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        // Get first page
        $firstResult = $this->paginationService->getMessagesCursor($room->id, null, 5);

        // Get newer messages using cursor
        $secondResult = $this->paginationService->getMessagesCursor(
            $room->id,
            $firstResult['prev_cursor'],
            5,
            'after'
        );

        $this->assertCount(4, $secondResult['messages']);
    }

    #[Test]
    public function it_gets_recent_messages()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create messages
        ChatMessage::factory()->count(10)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $result = $this->paginationService->getRecentMessages($room->id, 5);

        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertCount(5, $result['messages']);
    }

    #[Test]
    public function it_gets_messages_after_specific_message()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create messages
        $messages = ChatMessage::factory()->count(10)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $middleMessage = $messages[4]; // 5th message

        $result = $this->paginationService->getMessagesAfter($room->id, $middleMessage->id, 5);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());

        // All returned messages should have IDs greater than the middle message
        foreach ($result as $message) {
            $this->assertGreaterThan($middleMessage->id, $message->id);
        }
    }

    #[Test]
    public function it_searches_messages()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create messages with specific content
        ChatMessage::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'message' => 'Hello world test message',
        ]);

        ChatMessage::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'message' => 'Another message without keyword',
        ]);

        $result = $this->paginationService->searchMessages($room->id, 'test');

        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('next_cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertCount(1, $result['messages']);
        $this->assertStringContainsString('test', $result['messages']->first()->message);
    }

    #[Test]
    public function it_returns_empty_search_for_no_matches()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create messages without search term
        ChatMessage::factory()->count(5)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'message' => 'Hello world',
        ]);

        $result = $this->paginationService->searchMessages($room->id, 'nonexistent');

        $this->assertArrayHasKey('messages', $result);
        $this->assertCount(0, $result['messages']);
        $this->assertFalse($result['has_more']);
    }

    #[Test]
    public function it_gets_message_statistics()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create messages over the last few days
        ChatMessage::factory()->count(10)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $result = $this->paginationService->getMessageStatistics($room->id, 7);

        $this->assertArrayHasKey('total_messages', $result);
        $this->assertArrayHasKey('messages_per_day', $result);
        $this->assertArrayHasKey('active_users', $result);
        $this->assertEquals(10, $result['total_messages']);
    }

    #[Test]
    public function it_handles_invalid_cursor()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create some messages
        ChatMessage::factory()->count(5)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $result = $this->paginationService->getMessagesCursor($room->id, 'invalid-cursor');

        $this->assertArrayHasKey('messages', $result);
        $this->assertCount(5, $result['messages']);
    }

    #[Test]
    public function it_respects_max_page_size()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create more messages than the service max page size
        ChatMessage::factory()->count(150)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        // Try to get more than max page size
        $result = $this->paginationService->getMessagesCursor($room->id, null, 200);

        $this->assertCount(100, $result['messages']); // Should be limited to max page size
    }

    #[Test]
    public function it_handles_empty_room()
    {
        $room = ChatRoom::factory()->create();

        $result = $this->paginationService->getMessagesCursor($room->id);

        $this->assertArrayHasKey('messages', $result);
        $this->assertCount(0, $result['messages']);
        $this->assertFalse($result['has_more']);
        $this->assertNull($result['next_cursor']);
    }

    #[Test]
    public function it_generates_correct_cursors()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        ChatMessage::factory()->count(2)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $result = $this->paginationService->getMessagesCursor($room->id, null, 1);

        $this->assertNotNull($result['next_cursor']);
        $this->assertNotNull($result['prev_cursor']);
        $this->assertIsString($result['next_cursor']);
        $this->assertIsString($result['prev_cursor']);
    }

    #[Test]
    public function it_handles_search_with_cursor()
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        // Create messages with search term
        ChatMessage::factory()->count(10)->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'message' => 'test message content',
        ]);

        $result = $this->paginationService->searchMessages($room->id, 'test', null, 5);

        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('next_cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertCount(5, $result['messages']);
        $this->assertTrue($result['has_more']);
    }
}
