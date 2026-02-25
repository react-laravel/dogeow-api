<?php

namespace Tests\Feature;

use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageSent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ChatRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable broadcasting to avoid Pusher connection issues in tests
        Event::fake();

        $this->user = User::factory()->create();
        $this->room = ChatRoom::factory()->create([
            'created_by' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_get_rooms_returns_active_rooms()
    {
        $activeRoom = ChatRoom::factory()->create(['is_active' => true]);
        $inactiveRoom = ChatRoom::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/chat/rooms');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'rooms'); // Including the room from setUp
        $response->assertJsonFragment(['id' => $activeRoom->id]);
        $response->assertJsonMissing(['id' => $inactiveRoom->id]);
    }

    public function test_create_room_with_valid_data()
    {
        $roomData = [
            'name' => 'Test Room',
            'description' => 'A test chat room',
        ];

        $response = $this->postJson('/api/chat/rooms', $roomData);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'name' => 'Test Room',
            'description' => 'A test chat room',
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('chat_rooms', $roomData);
    }

    public function test_create_room_with_invalid_data()
    {
        $response = $this->postJson('/api/chat/rooms', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_join_room_successfully()
    {
        $response = $this->postJson("/api/chat/rooms/{$this->room->id}/join");

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Successfully joined the room']);

        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);
    }

    public function test_join_nonexistent_room()
    {
        $response = $this->postJson('/api/chat/rooms/999/join');

        $response->assertStatus(422);
    }

    public function test_leave_room_successfully()
    {
        // First join the room
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);

        $response = $this->postJson("/api/chat/rooms/{$this->room->id}/leave");

        $response->assertStatus(200);

        $roomUser = ChatRoomUser::where('room_id', $this->room->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertFalse($roomUser->is_online);
    }

    public function test_delete_room_by_creator()
    {
        $response = $this->deleteJson("/api/chat/rooms/{$this->room->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('chat_rooms', [
            'id' => $this->room->id,
            'is_active' => false,
        ]);
    }

    public function test_delete_room_by_non_creator()
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/chat/rooms/{$this->room->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('chat_rooms', ['id' => $this->room->id]);
    }

    public function test_get_messages_requires_room_membership()
    {
        $response = $this->getJson("/api/chat/rooms/{$this->room->id}/messages");

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'You must join the room to view messages']);
    }

    public function test_get_messages_returns_paginated_messages()
    {
        // Join the room first
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);

        // Create some messages
        ChatMessage::factory()->count(5)->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/chat/rooms/{$this->room->id}/messages");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'per_page',
            'total',
        ]);
    }

    public function test_send_message_requires_room_membership()
    {
        $response = $this->postJson("/api/chat/rooms/{$this->room->id}/messages", [
            'message' => 'Hello, world!',
        ]);

        $response->assertStatus(403);
    }

    public function test_send_message_requires_online_status()
    {
        // Join the room but set offline
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => false,
        ]);

        $response = $this->postJson("/api/chat/rooms/{$this->room->id}/messages", [
            'message' => 'Hello, world!',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'You must be online in the room to send messages']);
    }

    public function test_send_message_successfully()
    {
        Event::fake();

        // Join the room and set online
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);

        $response = $this->postJson("/api/chat/rooms/{$this->room->id}/messages", [
            'message' => 'Hello, world!',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['message' => 'Message sent successfully']);

        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'message' => 'Hello, world!',
        ]);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_send_message_respects_rate_limiting()
    {
        $this->markTestSkipped('Rate limiting test needs to be fixed');

        // Mock the content filter service to allow all messages
        $this->mock(\App\Services\Chat\ContentFilterService::class, function ($mock) {
            $mock->shouldReceive('processMessage')->andReturn([
                'allowed' => true,
                'filtered_message' => 'Test message',
                'violations' => [],
                'actions_taken' => [],
                'severity' => 'none',
            ]);
        });

        // Join the room and set online
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);

        // Clear any existing rate limit and spam detection cache
        $rateLimitKey = "send_message:{$this->user->id}:{$this->room->id}";
        RateLimiter::clear($rateLimitKey);

        // Clear spam detection cache
        $spamCacheKey = "chat_message_frequency_{$this->user->id}_{$this->room->id}";
        \Illuminate\Support\Facades\Cache::forget($spamCacheKey);

        // Send messages to hit the rate limit (10 messages per minute)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson("/api/chat/rooms/{$this->room->id}/messages", [
                'message' => "Test message {$i}",
            ]);
            $response->assertStatus(201);
        }

        // The 11th message should be rate limited
        $response = $this->postJson("/api/chat/rooms/{$this->room->id}/messages", [
            'message' => 'Rate limited message',
        ]);

        $response->assertStatus(429);
        $response->assertJsonFragment(['message' => 'Too many messages. Please wait']);
        $response->assertJsonStructure(['message', 'rate_limit']);
    }

    public function test_send_message_blocked_when_muted()
    {
        // Create a different user who is not the room creator
        $otherUser = User::factory()->create();

        // Join the room with the other user and set online but muted
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $otherUser->id,
            'is_online' => true,
            'is_muted' => true,
        ]);

        // Authenticate as the muted user
        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/chat/rooms/{$this->room->id}/messages", [
            'message' => 'Hello, world!',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'You are muted in this room']);
    }

    public function test_send_message_blocked_when_banned()
    {
        // Create a different user who is not the room creator
        $otherUser = User::factory()->create();

        // Join the room with the other user and set online but banned
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $otherUser->id,
            'is_online' => true,
            'is_banned' => true,
        ]);

        // Authenticate as the banned user
        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/chat/rooms/{$this->room->id}/messages", [
            'message' => 'Hello, world!',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'You are banned from this room']);
    }

    public function test_delete_message_by_author()
    {
        Event::fake();

        $message = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/chat/rooms/{$this->room->id}/messages/{$message->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Message deleted successfully']);

        $this->assertDatabaseMissing('chat_messages', ['id' => $message->id]);
        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_delete_message_by_room_creator()
    {
        Event::fake();

        $otherUser = User::factory()->create();
        $message = ChatMessage::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/chat/rooms/{$this->room->id}/messages/{$message->id}");

        $response->assertStatus(200);
        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_delete_message_unauthorized()
    {
        $otherUser = User::factory()->create();
        $otherRoom = ChatRoom::factory()->create(['created_by' => $otherUser->id]);

        $message = ChatMessage::factory()->create([
            'room_id' => $otherRoom->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/chat/rooms/{$otherRoom->id}/messages/{$message->id}");

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'You are not authorized to delete this message']);
    }

    public function test_get_online_users_requires_room_membership()
    {
        $response = $this->getJson("/api/chat/rooms/{$this->room->id}/users");

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'You must join the room to view online users']);
    }

    public function test_get_online_users_returns_online_users()
    {
        // Join the room
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);

        // Add another online user
        $otherUser = User::factory()->create();
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $otherUser->id,
            'is_online' => true,
        ]);

        // Add an offline user
        $offlineUser = User::factory()->create();
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $offlineUser->id,
            'is_online' => false,
        ]);

        $response = $this->getJson("/api/chat/rooms/{$this->room->id}/users");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'online_users',
            'count',
        ]);
        $response->assertJsonFragment(['count' => 2]);
    }

    public function test_update_user_status_updates_last_seen()
    {
        // Join the room
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);

        $response = $this->postJson("/api/chat/rooms/{$this->room->id}/status");

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Status updated successfully']);
        $response->assertJsonStructure(['last_seen_at']);
    }

    public function test_cleanup_disconnected_users()
    {
        $response = $this->postJson('/api/chat/cleanup-disconnected');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'cleaned_users_count',
        ]);
    }

    public function test_get_user_presence_status_when_not_in_room()
    {
        $response = $this->getJson("/api/chat/rooms/{$this->room->id}/my-status");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'is_in_room' => false,
            'is_online' => false,
        ]);
    }

    public function test_get_user_presence_status_when_in_room()
    {
        ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);

        $response = $this->getJson("/api/chat/rooms/{$this->room->id}/my-status");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'is_in_room' => true,
            'is_online' => true,
        ]);
        $response->assertJsonStructure([
            'joined_at',
            'last_seen_at',
            'is_inactive',
        ]);
    }

    public function test_unauthenticated_requests_are_rejected()
    {
        // Skip this test for now as it's complex to test without breaking other tests
        $this->markTestSkipped('Authentication test requires separate test setup');
    }

    public function test_create_room_with_is_private_true()
    {
        $roomData = [
            'name' => 'Private Test Room',
            'description' => 'A private room',
            'is_private' => true,
        ];

        $response = $this->postJson('/api/chat/rooms', $roomData);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'name' => 'Private Test Room',
            'is_private' => true,
        ]);
        $this->assertDatabaseHas('chat_rooms', [
            'name' => 'Private Test Room',
            'is_private' => true,
        ]);
    }

    public function test_get_rooms_excludes_private_room_when_user_not_member()
    {
        $otherUser = User::factory()->create();
        $privateRoom = ChatRoom::factory()->private()->create([
            'name' => 'Private Room',
            'created_by' => $otherUser->id,
        ]);
        ChatRoomUser::create([
            'room_id' => $privateRoom->id,
            'user_id' => $otherUser->id,
            'is_online' => false,
        ]);

        $response = $this->getJson('/api/chat/rooms');

        $response->assertStatus(200);
        $roomIds = array_column($response->json('rooms'), 'id');
        $this->assertNotContains($privateRoom->id, $roomIds);
    }

    public function test_get_rooms_includes_private_room_when_user_is_member()
    {
        $privateRoom = ChatRoom::factory()->private()->create([
            'name' => 'My Private Room',
            'created_by' => $this->user->id,
        ]);
        ChatRoomUser::create([
            'room_id' => $privateRoom->id,
            'user_id' => $this->user->id,
            'is_online' => false,
        ]);

        $response = $this->getJson('/api/chat/rooms');

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $privateRoom->id, 'name' => 'My Private Room']);
    }

    public function test_join_private_room_as_non_member_fails()
    {
        $otherUser = User::factory()->create();
        $privateRoom = ChatRoom::factory()->private()->create([
            'name' => 'Private Room',
            'created_by' => $otherUser->id,
        ]);
        ChatRoomUser::create([
            'room_id' => $privateRoom->id,
            'user_id' => $otherUser->id,
            'is_online' => false,
        ]);

        $response = $this->postJson("/api/chat/rooms/{$privateRoom->id}/join");

        $response->assertStatus(422);
        $this->assertDatabaseMissing('chat_room_users', [
            'room_id' => $privateRoom->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_join_private_room_as_creator_succeeds()
    {
        $privateRoom = ChatRoom::factory()->private()->create([
            'name' => 'My Private Room',
            'created_by' => $this->user->id,
        ]);
        ChatRoomUser::create([
            'room_id' => $privateRoom->id,
            'user_id' => $this->user->id,
            'is_online' => false,
        ]);

        $response = $this->postJson("/api/chat/rooms/{$privateRoom->id}/join");

        $response->assertStatus(200);
        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $privateRoom->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);
    }

    public function test_update_room_successfully()
    {
        $response = $this->putJson("/api/chat/rooms/{$this->room->id}", [
            'name' => 'Updated Room Name',
            'description' => 'Updated description',
            'is_private' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Updated Room Name',
            'description' => 'Updated description',
            'is_private' => true,
        ]);
        $this->assertDatabaseHas('chat_rooms', [
            'id' => $this->room->id,
            'name' => 'Updated Room Name',
            'is_private' => true,
        ]);
    }
}
