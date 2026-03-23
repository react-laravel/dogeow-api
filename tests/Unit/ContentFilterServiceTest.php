<?php

namespace Tests\Unit;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatRoom;
use App\Models\User;
use App\Services\Chat\ContentFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContentFilterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ContentFilterService $contentFilterService;

    protected User $user;

    protected ChatRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentFilterService = new ContentFilterService;

        // Create test user and room
        $this->user = User::factory()->create();
        $this->room = ChatRoom::factory()->create(['created_by' => $this->user->id]);
    }

    public function test_inappropriate_content_detection()
    {
        $message = 'This is a stupid spam message with hate';

        $result = $this->contentFilterService->checkInappropriateContent($message);

        $this->assertTrue($result['has_violations']);
        $this->assertCount(3, $result['violations']); // stupid, spam, hate
        $this->assertEquals('high', $result['severity']); // hate is high severity
        $this->assertTrue($result['action_required']);
        $this->assertStringContainsString('[filtered]', $result['filtered_message']);
    }

    public function test_word_replacement()
    {
        $message = "Don't spam me with stupid content";

        $result = $this->contentFilterService->checkInappropriateContent($message);

        $this->assertTrue($result['has_violations']);
        $this->assertStringContainsString('****', $result['filtered_message']); // spam -> ****
        $this->assertStringContainsString('[filtered]', $result['filtered_message']); // stupid -> [filtered]
    }

    public function test_spam_detection_high_frequency()
    {
        $userId = $this->user->id;
        $roomId = $this->room->id;

        // Simulate rapid message sending
        $cacheKey = "chat_message_frequency_{$userId}_{$roomId}";
        $timestamps = [];
        for ($i = 0; $i < 6; $i++) {
            $timestamps[] = now()->timestamp;
        }
        Cache::put($cacheKey, $timestamps, 300);

        $result = $this->contentFilterService->detectSpam('Hello', $userId, $roomId);

        $this->assertTrue($result['is_spam']);
        $this->assertEquals('high', $result['severity']);
        $this->assertTrue($result['action_required']);

        $frequencyViolation = collect($result['violations'])->firstWhere('type', 'high_frequency');
        $this->assertNotNull($frequencyViolation);
        $this->assertEquals('high', $frequencyViolation['severity']);
    }

    public function test_spam_detection_duplicate_messages()
    {
        $userId = $this->user->id;
        $roomId = $this->room->id;
        $message = 'Hello world';

        // Create duplicate messages in database
        for ($i = 0; $i < 3; $i++) {
            ChatMessage::create([
                'room_id' => $roomId,
                'user_id' => $userId,
                'message' => $message,
                'message_type' => 'text',
                'created_at' => now()->subMinutes(2),
            ]);
        }

        $result = $this->contentFilterService->detectSpam($message, $userId, $roomId);

        $this->assertTrue($result['is_spam']);
        $duplicateViolation = collect($result['violations'])->firstWhere('type', 'duplicate_message');
        $this->assertNotNull($duplicateViolation);
        $this->assertEquals('medium', $duplicateViolation['severity']);
    }

    public function test_spam_detection_excessive_caps()
    {
        $message = 'THIS IS A VERY LOUD MESSAGE WITH LOTS OF CAPS';

        $result = $this->contentFilterService->detectSpam($message, $this->user->id, $this->room->id);

        $capsViolation = collect($result['violations'])->firstWhere('type', 'excessive_caps');
        $this->assertNotNull($capsViolation);
        $this->assertEquals('low', $capsViolation['severity']);
        $this->assertTrue($capsViolation['details']['caps_ratio'] > 0.7);
    }

    public function test_spam_detection_character_repetition()
    {
        $message = 'Hellooooooo worlddddddd!!!!!';

        $result = $this->contentFilterService->detectSpam($message, $this->user->id, $this->room->id);

        $repetitionViolation = collect($result['violations'])->firstWhere('type', 'character_repetition');
        $this->assertNotNull($repetitionViolation);
        $this->assertEquals('low', $repetitionViolation['severity']);
    }

    public function test_spam_detection_url_spam()
    {
        $message = 'Check out http://example.com and http://test.com and http://spam.com';

        $result = $this->contentFilterService->detectSpam($message, $this->user->id, $this->room->id);

        $urlViolation = collect($result['violations'])->firstWhere('type', 'url_spam');
        $this->assertNotNull($urlViolation);
        $this->assertEquals('medium', $urlViolation['severity']);
        $this->assertEquals(3, $urlViolation['details']['url_count']);
    }

    public function test_suspicious_url_detection()
    {
        $message = 'Click here for free money: http://bit.ly/freemoney';

        $result = $this->contentFilterService->detectSpam($message, $this->user->id, $this->room->id);

        $urlViolation = collect($result['violations'])->firstWhere('type', 'url_spam');
        $this->assertNotNull($urlViolation);
        $this->assertTrue($urlViolation['details']['suspicious_urls'] > 0);
    }

    public function test_process_message_blocks_inappropriate_content()
    {
        $message = 'This is hate speech with violence';

        $result = $this->contentFilterService->processMessage($message, $this->user->id, $this->room->id);

        $this->assertFalse($result['allowed']);
        $this->assertContains('message_blocked', $result['actions_taken']);
        $this->assertEquals('high', $result['severity']);
        $this->assertArrayHasKey('content', $result['violations']);
    }

    public function test_process_message_blocks_spam()
    {
        $userId = $this->user->id;
        $roomId = $this->room->id;

        // Simulate high frequency spam
        $cacheKey = "chat_message_frequency_{$userId}_{$roomId}";
        $timestamps = array_fill(0, 6, now()->timestamp);
        Cache::put($cacheKey, $timestamps, 300);

        $result = $this->contentFilterService->processMessage('Hello', $userId, $roomId);

        $this->assertFalse($result['allowed']);
        $this->assertContains('spam_blocked', $result['actions_taken']);
        $this->assertContains('user_auto_muted', $result['actions_taken']);
        $this->assertEquals('high', $result['severity']);
    }

    public function test_process_message_allows_clean_content()
    {
        $message = 'This is a perfectly normal message';

        $result = $this->contentFilterService->processMessage($message, $this->user->id, $this->room->id);

        $this->assertTrue($result['allowed']);
        $this->assertEquals($message, $result['filtered_message']);
        $this->assertEmpty($result['violations']);
        $this->assertEmpty($result['actions_taken']);
        $this->assertEquals('none', $result['severity']);
    }

    public function test_process_message_filters_but_allows_low_severity()
    {
        $message = 'This is a spam message'; // Only one low-severity word

        $result = $this->contentFilterService->processMessage($message, $this->user->id, $this->room->id);

        $this->assertTrue($result['allowed']); // Should be allowed but filtered
        $this->assertStringContainsString('****', $result['filtered_message']);
        $this->assertArrayHasKey('content', $result['violations']);
        $this->assertEquals('low', $result['severity']);
    }

    public function test_get_filter_stats()
    {
        // This would require creating moderation actions in the database
        // For now, just test that the method returns the expected structure
        $stats = $this->contentFilterService->getFilterStats($this->room->id, 7);

        $this->assertArrayHasKey('total_actions', $stats);
        $this->assertArrayHasKey('content_filter_actions', $stats);
        $this->assertArrayHasKey('spam_detection_actions', $stats);
        $this->assertArrayHasKey('severity_breakdown', $stats);
        $this->assertArrayHasKey('top_violations', $stats);
        $this->assertArrayHasKey('affected_users', $stats);
        $this->assertArrayHasKey('period_days', $stats);
        $this->assertEquals(7, $stats['period_days']);
    }
}
