<?php

namespace Tests\Unit\Services;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use App\Services\Chat\ChatCacheService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatPaginationService;
use App\Services\Chat\ChatRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatRoomServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatRoomService $service;

    private ChatCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheService = Mockery::mock(ChatCacheService::class);
        $this->service = new ChatRoomService(
            new ChatMessageService(new ChatPaginationService),
            $this->cacheService
        );
    }

    public function test_validate_room_data_rejects_duplicate_active_room_name(): void
    {
        ChatRoom::factory()->create([
            'name' => 'Existing Room',
            'is_active' => true,
        ]);

        $result = $this->service->validateRoomData([
            'name' => 'Existing Room',
            'description' => 'Another room',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('该房间名称已存在', $result['errors']);
    }

    public function test_create_room_creates_membership_and_system_message(): void
    {
        $creator = User::factory()->create();

        $result = $this->service->createRoom([
            'name' => 'Core Room',
            'description' => 'Room for tests',
            'is_private' => true,
        ], $creator->id);

        $this->assertTrue($result['success']);
        $room = $result['room'];
        $this->assertSame('Core Room', $room->name);
        $this->assertTrue($room->is_private);

        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $room->id,
            'user_id' => $creator->id,
            'is_online' => true,
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $room->id,
            'user_id' => $creator->id,
            'message_type' => 'system',
            'message' => "Room 'Core Room' has been created",
        ]);
    }

    public function test_delete_room_rejects_when_user_has_no_permission(): void
    {
        $creator = User::factory()->create();
        $outsider = User::factory()->create();
        $room = ChatRoom::factory()->create([
            'created_by' => $creator->id,
        ]);

        $result = $this->service->deleteRoom($room->id, $outsider->id);

        $this->assertFalse($result['success']);
        $this->assertSame(['You do not have permission to delete this room'], $result['errors']);
    }

    public function test_delete_room_rejects_when_other_online_users_are_present(): void
    {
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $room = ChatRoom::factory()->create([
            'created_by' => $creator->id,
        ]);

        ChatRoomUser::factory()->online()->create([
            'room_id' => $room->id,
            'user_id' => $otherUser->id,
        ]);

        $result = $this->service->deleteRoom($room->id, $creator->id);

        $this->assertFalse($result['success']);
        $this->assertSame(
            ['Cannot delete room with active users. Please wait for all users to leave.'],
            $result['errors']
        );
    }

    public function test_delete_room_marks_room_inactive_and_removes_memberships(): void
    {
        $creator = User::factory()->create(['name' => 'Creator']);
        $room = ChatRoom::factory()->create([
            'name' => 'Disposable Room',
            'created_by' => $creator->id,
            'is_active' => true,
        ]);

        ChatRoomUser::factory()->online()->create([
            'room_id' => $room->id,
            'user_id' => $creator->id,
        ]);
        ChatRoomUser::factory()->offline()->create([
            'room_id' => $room->id,
        ]);

        $result = $this->service->deleteRoom($room->id, $creator->id);

        $this->assertTrue($result['success']);
        $this->assertFalse($room->fresh()->is_active);
        $this->assertDatabaseMissing('chat_room_users', [
            'room_id' => $room->id,
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $room->id,
            'user_id' => $creator->id,
            'message_type' => 'system',
            'message' => "Room 'Disposable Room' is being deleted",
        ]);
    }

    public function test_update_room_rejects_duplicate_name(): void
    {
        $creator = User::factory()->create();
        $room = ChatRoom::factory()->create([
            'created_by' => $creator->id,
            'name' => 'Original',
        ]);
        ChatRoom::factory()->create([
            'name' => 'Taken Name',
            'is_active' => true,
        ]);

        $result = $this->service->updateRoom($room->id, [
            'name' => 'Taken Name',
            'description' => 'new description',
        ], $creator->id);

        $this->assertFalse($result['success']);
        $this->assertContains('该房间名称已存在', $result['errors']);
    }

    public function test_update_room_rename_creates_system_message(): void
    {
        $creator = User::factory()->create();
        $room = ChatRoom::factory()->create([
            'created_by' => $creator->id,
            'name' => 'Old Name',
            'description' => 'Old description',
        ]);

        $result = $this->service->updateRoom($room->id, [
            'name' => 'New Name',
            'description' => 'Updated description',
            'is_private' => true,
        ], $creator->id);

        $this->assertTrue($result['success']);
        $this->assertSame('New Name', $result['room']->name);
        $this->assertTrue((bool) $result['room']->is_private);
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $room->id,
            'user_id' => $creator->id,
            'message_type' => 'system',
            'message' => "Room renamed from 'Old Name' to 'New Name'",
        ]);
    }

    public function test_get_room_stats_returns_counts_messages_and_peak_hours(): void
    {
        $creator = User::factory()->create();
        $topSpeaker = User::factory()->create(['name' => 'Top Speaker']);
        $otherUser = User::factory()->create();
        $room = ChatRoom::factory()->create([
            'created_by' => $creator->id,
        ]);

        ChatRoomUser::factory()->online()->create([
            'room_id' => $room->id,
            'user_id' => $creator->id,
        ]);
        ChatRoomUser::factory()->online()->create([
            'room_id' => $room->id,
            'user_id' => $topSpeaker->id,
        ]);
        ChatRoomUser::factory()->offline()->create([
            'room_id' => $room->id,
            'user_id' => $otherUser->id,
        ]);

        ChatMessage::factory()->count(3)->create([
            'room_id' => $room->id,
            'user_id' => $topSpeaker->id,
            'message_type' => ChatMessage::TYPE_TEXT,
            'created_at' => now()->subHour()->minute(15),
        ]);
        ChatMessage::factory()->create([
            'room_id' => $room->id,
            'user_id' => $otherUser->id,
            'message_type' => ChatMessage::TYPE_TEXT,
            'created_at' => now()->subHours(2),
        ]);
        ChatMessage::factory()->create([
            'room_id' => $room->id,
            'user_id' => $creator->id,
            'message_type' => ChatMessage::TYPE_SYSTEM,
            'created_at' => now()->subMinutes(10),
        ]);

        $stats = $this->service->getRoomStats($room->id);

        $this->assertSame($room->id, $stats['room']->id);
        $this->assertSame(3, $stats['total_users']);
        $this->assertSame(2, $stats['online_users']);
        $this->assertSame(5, $stats['messages']['total_messages']);
        $this->assertSame(4, $stats['messages']['text_messages']);
        $this->assertSame(1, $stats['messages']['system_messages']);
        $this->assertGreaterThanOrEqual(1, $stats['recent_activity_24h']);
        $this->assertNotNull($stats['last_activity']);
        $this->assertGreaterThan(0, $stats['peak_hours']->count());
    }

    public function test_get_active_rooms_delegates_to_cache_service(): void
    {
        $expected = collect([ChatRoom::factory()->make(['name' => 'Cached Room'])]);

        $this->cacheService->shouldReceive('getRoomList')
            ->once()
            ->with(99)
            ->andReturn($expected);

        $rooms = $this->service->getActiveRooms(99);

        $this->assertSame($expected, $rooms);
    }
}
