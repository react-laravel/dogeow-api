<?php

namespace Tests\Unit\Services;

use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use App\Services\Chat\ChatCacheService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatPaginationService;
use App\Services\Chat\ChatPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class ChatPresenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatPresenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
        Event::fake();

        $this->service = new ChatPresenceService(
            new ChatMessageService(new ChatPaginationService),
            Mockery::mock(ChatCacheService::class)
        );
    }

    public function test_update_user_status_creates_membership_when_user_comes_online(): void
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        $result = $this->service->updateUserStatus($room->id, $user->id, true);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'is_online' => true,
        ]);
        $this->assertSame($user->id, $result['room_user']->user->id);
    }

    public function test_update_user_status_returns_error_when_offline_user_is_not_in_room(): void
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        $result = $this->service->updateUserStatus($room->id, $user->id, false);

        $this->assertFalse($result['success']);
        $this->assertSame(['User not found in room'], $result['errors']);
    }

    public function test_join_room_reactivates_existing_member_without_creating_system_message(): void
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        ChatRoomUser::factory()->offline()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $result = $this->service->joinRoom($room->id, $user->id);

        $this->assertTrue($result['success']);
        $this->assertSame('User is already a member of this room', $result['message']);
        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'is_online' => true,
        ]);
        $this->assertDatabaseMissing('chat_messages', [
            'room_id' => $room->id,
            'message_type' => 'system',
            'message' => "{$user->name} joined the room",
        ]);
    }

    public function test_join_room_rejects_private_room_for_non_member(): void
    {
        $room = ChatRoom::factory()->private()->create();
        $user = User::factory()->create();

        $result = $this->service->joinRoom($room->id, $user->id);

        $this->assertFalse($result['success']);
        $this->assertSame(['Private rooms are only joinable by existing members.'], $result['errors']);
    }

    public function test_leave_room_marks_user_offline_and_creates_system_message(): void
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create(['name' => 'Leave User']);

        ChatRoomUser::factory()->online()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $result = $this->service->leaveRoom($room->id, $user->id);

        $this->assertTrue($result['success']);
        $this->assertSame('Successfully left the room', $result['message']);
        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'is_online' => false,
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'message_type' => 'system',
            'message' => "{$user->name} left the room",
        ]);
    }

    public function test_process_heartbeat_updates_last_seen_and_online_status(): void
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();
        $roomUser = ChatRoomUser::factory()->offline()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'last_seen_at' => now()->subHour(),
        ]);

        $result = $this->service->processHeartbeat($room->id, $user->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($roomUser->fresh()->is_online);
        $this->assertTrue($roomUser->fresh()->last_seen_at->greaterThan(now()->subMinute()));
    }

    public function test_cleanup_inactive_users_marks_them_offline_and_logs_system_message(): void
    {
        $staleUser = User::factory()->create(['name' => 'Stale User']);
        $freshUser = User::factory()->create(['name' => 'Fresh User']);
        $room = ChatRoom::factory()->create();

        $staleRoomUser = ChatRoomUser::factory()->online()->create([
            'room_id' => $room->id,
            'user_id' => $staleUser->id,
            'last_seen_at' => now()->subMinutes(10),
        ]);
        $freshRoomUser = ChatRoomUser::factory()->online()->create([
            'room_id' => $room->id,
            'user_id' => $freshUser->id,
            'last_seen_at' => now()->subMinute(),
        ]);

        $result = $this->service->cleanupInactiveUsers();

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['cleaned_count']);
        $this->assertFalse($staleRoomUser->fresh()->is_online);
        $this->assertTrue($freshRoomUser->fresh()->is_online);
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $room->id,
            'user_id' => $staleUser->id,
            'message_type' => 'system',
            'message' => "{$staleUser->name} went offline due to inactivity",
        ]);
    }
}
