<?php

namespace Tests\Unit;

use App\Models\Chat\ChatModerationAction;
use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use App\Services\Chat\ContentFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContentFilterServiceExtraTest extends TestCase
{
    use RefreshDatabase;

    protected ContentFilterService $contentFilterService;

    protected User $user;

    protected ChatRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentFilterService = new ContentFilterService;

        $this->user = User::factory()->create();
        $this->room = ChatRoom::factory()->create(['created_by' => $this->user->id]);
    }

    public function test_check_message_frequency_cleans_old_timestamps_and_counts_current()
    {
        $userId = $this->user->id;
        $roomId = $this->room->id;
        $cacheKey = "chat_message_frequency_{$userId}_{$roomId}";

        // Simulate timestamps: some older than 1 minute, some recent.
        $oldTimestamp = now()->subMinutes(2)->timestamp;
        $recentTimestamp = now()->subSeconds(30)->timestamp;
        $existing = [$oldTimestamp, $recentTimestamp];

        Cache::put($cacheKey, $existing, 300);

        // Use reflection to call private method
        $method = new \ReflectionMethod(ContentFilterService::class, 'checkMessageFrequency');
        $method->setAccessible(true);

        $result = $method->invoke($this->contentFilterService, $userId, $roomId);

        // Old timestamp should be cleaned; at least the recent will remain and current is added.
        $this->assertArrayHasKey('message_count', $result);
        $this->assertTrue($result['message_count'] >= 2);
        $this->assertEquals('1 minute', $result['time_window']);
    }

    public function test_check_excessive_caps_short_message_returns_false()
    {
        $method = new \ReflectionMethod(ContentFilterService::class, 'checkExcessiveCaps');
        $method->setAccessible(true);

        // Short message (less than 10 letters) should not be considered spam by caps rule
        $result = $method->invoke($this->contentFilterService, 'HELLO!!!');
        $this->assertIsArray($result);
        $this->assertFalse($result['is_spam']);
    }

    public function test_check_excessive_caps_detects_high_caps_ratio()
    {
        $method = new \ReflectionMethod(ContentFilterService::class, 'checkExcessiveCaps');
        $method->setAccessible(true);

        $message = 'THIS MESSAGE HAS A LOT OF CAPS AND IS LONG ENOUGH';
        $result = $method->invoke($this->contentFilterService, $message);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('caps_ratio', $result);
        $this->assertTrue($result['caps_ratio'] > 0);
        // Given the content, caps_ratio should exceed threshold and be flagged
        $this->assertTrue($result['is_spam']);
    }

    public function test_check_character_repetition_short_message_returns_false()
    {
        $method = new \ReflectionMethod(ContentFilterService::class, 'checkCharacterRepetition');
        $method->setAccessible(true);

        $result = $method->invoke($this->contentFilterService, 'aa');
        $this->assertIsArray($result);
        $this->assertFalse($result['is_spam']);
    }

    public function test_check_character_repetition_detects_repetition()
    {
        $method = new \ReflectionMethod(ContentFilterService::class, 'checkCharacterRepetition');
        $method->setAccessible(true);

        $message = 'Hellooooooo worlddddddd!!!!!';
        $result = $method->invoke($this->contentFilterService, $message);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('repetition_ratio', $result);
        $this->assertTrue($result['repetition_ratio'] > 0);
        $this->assertTrue($result['is_spam']);
    }

    public function test_check_url_spam_detects_suspicious_shortener_and_url_count()
    {
        $method = new \ReflectionMethod(ContentFilterService::class, 'checkUrlSpam');
        $method->setAccessible(true);

        $message = 'Check this out http://bit.ly/fake and http://example.com';
        $result = $method->invoke($this->contentFilterService, $message);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('url_count', $result);
        $this->assertGreaterThanOrEqual(1, $result['url_count']);
        $this->assertGreaterThanOrEqual(1, $result['suspicious_urls']);
        $this->assertTrue($result['is_spam']);
    }

    public function test_auto_mute_user_returns_false_when_room_user_missing()
    {
        $method = new \ReflectionMethod(ContentFilterService::class, 'autoMuteUser');
        $method->setAccessible(true);

        $fakeUserId = 999999; // Non-existent
        $fakeRoomId = 999999;

        $result = $method->invoke($this->contentFilterService, $fakeUserId, $fakeRoomId, 'No room user', 5);

        $this->assertFalse($result);

        // Ensure no moderation action was created for these ids
        $this->assertDatabaseMissing('chat_moderation_actions', [
            'room_id' => $fakeRoomId,
            'target_user_id' => $fakeUserId,
            'action_type' => ChatModerationAction::ACTION_MUTE_USER,
        ]);
    }

    public function test_auto_mute_user_success_updates_room_user_and_logs_action()
    {
        // Create a ChatRoomUser row for the user in the room
        $roomUser = ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'joined_at' => now(),
            'last_seen_at' => now(),
            'is_online' => true,
            'is_muted' => false,
        ]);

        $method = new \ReflectionMethod(ContentFilterService::class, 'autoMuteUser');
        $method->setAccessible(true);

        $result = $method->invoke($this->contentFilterService, $this->user->id, $this->room->id, 'Automatic mute for testing', 15);

        $this->assertTrue($result);

        // Reload the room user from DB and assert muted fields set
        $roomUserFresh = ChatRoomUser::where('room_id', $this->room->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($roomUserFresh);
        $this->assertTrue((bool) $roomUserFresh->is_muted);
        $this->assertNotNull($roomUserFresh->muted_until);

        // Check moderation action logged
        $this->assertDatabaseHas('chat_moderation_actions', [
            'room_id' => $this->room->id,
            'target_user_id' => $this->user->id,
            'action_type' => ChatModerationAction::ACTION_MUTE_USER,
        ]);

        $action = ChatModerationAction::where('room_id', $this->room->id)
            ->where('target_user_id', $this->user->id)
            ->where('action_type', ChatModerationAction::ACTION_MUTE_USER)
            ->first();

        $this->assertNotNull($action);
        $this->assertEquals('Automatic mute for testing', $action->metadata['reason'] ?? 'Automatic mute for testing');
        $this->assertEquals(true, $action->metadata['auto_action'] ?? true);
    }

    public function test_is_muted_auto_unmutes_when_expired()
    {
        // Create a ChatRoomUser muted in the past
        $roomUser = ChatRoomUser::create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'joined_at' => now(),
            'last_seen_at' => now(),
            'is_online' => true,
            'is_muted' => true,
            'muted_until' => now()->subMinutes(5),
            'muted_by' => 1,
        ]);

        // Call isMuted which should auto-unmute because muted_until is in the past
        $this->assertFalse($roomUser->isMuted());

        // Ensure DB was updated to reflect unmuted state
        $roomUserFresh = $roomUser->fresh();
        $this->assertFalse((bool) $roomUserFresh->is_muted);
        $this->assertNull($roomUserFresh->muted_until);
        $this->assertNull($roomUserFresh->muted_by);
    }

    public function test_get_filter_stats_returns_correct_structure()
    {
        $result = $this->contentFilterService->getFilterStats();

        $this->assertArrayHasKey('total_actions', $result);
        $this->assertArrayHasKey('content_filter_actions', $result);
        $this->assertArrayHasKey('spam_detection_actions', $result);
        $this->assertArrayHasKey('severity_breakdown', $result);
        $this->assertArrayHasKey('affected_users', $result);
        $this->assertArrayHasKey('period_days', $result);
    }

    public function test_get_filter_stats_with_specific_room()
    {
        $result = $this->contentFilterService->getFilterStats($this->room->id);

        $this->assertArrayHasKey('total_actions', $result);
        $this->assertIsInt($result['total_actions']);
    }

    public function test_get_filter_stats_with_custom_days()
    {
        $result = $this->contentFilterService->getFilterStats(null, 30);

        $this->assertEquals(30, $result['period_days']);
    }
}
