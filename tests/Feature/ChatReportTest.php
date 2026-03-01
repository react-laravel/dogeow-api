<?php

namespace Tests\Feature;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageReport;
use App\Models\Chat\ChatModerationAction;
use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $admin;

    protected ChatRoom $room;

    protected ChatMessage $message;

    protected function setUp(): void
    {
        parent::setUp();

        // Create system user with ID 1 for auto-moderation
        User::factory()->create(['id' => 1, 'name' => 'System', 'email' => 'system@example.com']);

        $this->user = User::factory()->create();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->room = ChatRoom::factory()->create(['created_by' => $this->admin->id]);

        ChatRoomUser::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->admin->id,
            'is_online' => true,
        ]);
        ChatRoomUser::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'is_online' => true,
        ]);

        // Create a message from admin that user can report
        $this->message = ChatMessage::create([
            'room_id' => $this->room->id,
            'user_id' => $this->admin->id,
            'message' => 'This is a test message',
            'message_type' => 'text',
        ]);
    }

    public function test_user_can_report_message()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
            'reason' => 'This message contains inappropriate content',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'report' => [
                    'id',
                    'message_id',
                    'reported_by',
                    'room_id',
                    'report_type',
                    'reason',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('chat_message_reports', [
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);
    }

    public function test_user_cannot_report_own_message()
    {
        Sanctum::actingAs($this->admin); // Admin trying to report their own message

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Testing self-report',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot report your own message',
            ]);
    }

    public function test_user_cannot_report_same_message_twice()
    {
        Sanctum::actingAs($this->user);

        // First report
        $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'First report',
        ])->assertStatus(201);

        // Second report should fail
        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_HARASSMENT,
            'reason' => 'Second report',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You have already reported this message',
            ]);
    }

    public function test_admin_can_view_room_reports()
    {
        // Create a report
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Test report',
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/chat/reports/rooms/{$this->room->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'reports' => [
                    '*' => [
                        'id',
                        'message_id',
                        'reported_by',
                        'report_type',
                        'reason',
                        'status',
                        'reporter',
                        'message',
                    ],
                ],
                'pagination',
            ]);
    }

    public function test_non_admin_cannot_view_reports()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/chat/reports/rooms/{$this->room->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You are not authorized to view reports for this room',
            ]);
    }

    public function test_admin_can_review_report()
    {
        $report = ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
            'reason' => 'Test report',
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/chat/reports/{$report->id}/review", [
            'action' => 'resolve',
            'notes' => 'Report reviewed and resolved',
            'delete_message' => false,
            'mute_user' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'report',
                'action',
                'actions_performed',
            ]);

        $this->assertDatabaseHas('chat_message_reports', [
            'id' => $report->id,
            'status' => ChatMessageReport::STATUS_RESOLVED,
            'reviewed_by' => $this->admin->id,
            'review_notes' => 'Report reviewed and resolved',
        ]);
    }

    public function test_report_stats_for_admin()
    {
        // Create some test reports
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Spam report',
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/chat/reports/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_reports',
                'pending_reports',
                'resolved_reports',
                'dismissed_reports',
                'report_types',
                'severity_breakdown',
                'top_reporters',
                'top_reported_users',
                'content_filter',
                'period_days',
            ]);
    }

    public function test_admin_can_view_all_reports()
    {
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Global report listing',
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/chat/reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'reports' => [
                    '*' => [
                        'id',
                        'room_id',
                        'report_type',
                        'status',
                        'reporter',
                        'room',
                    ],
                ],
                'pagination',
            ]);
    }

    public function test_non_admin_cannot_view_all_reports()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/chat/reports');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You are not authorized to view all reports',
            ]);
    }

    public function test_admin_can_filter_room_reports()
    {
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Should remain after filter',
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => User::factory()->create()->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_HARASSMENT,
            'reason' => 'Should be filtered out',
            'status' => ChatMessageReport::STATUS_RESOLVED,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/chat/reports/rooms/{$this->room->id}?status=pending&report_type=spam");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'reports');
        $response->assertJsonPath('reports.0.report_type', ChatMessageReport::TYPE_SPAM);
        $response->assertJsonPath('reports.0.status', ChatMessageReport::STATUS_PENDING);
    }

    public function test_admin_can_dismiss_report_and_apply_actions()
    {
        $report = ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_HARASSMENT,
            'reason' => 'Dismiss with actions',
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/chat/reports/{$report->id}/review", [
            'action' => 'dismiss',
            'notes' => 'Handled manually',
            'delete_message' => true,
            'mute_user' => true,
            'mute_duration' => 30,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('action', 'dismiss');
        $response->assertJsonPath('actions_performed.0', 'message_deleted');
        $response->assertJsonPath('actions_performed.1', 'user_muted');

        $this->assertDatabaseHas('chat_message_reports', [
            'id' => $report->id,
            'status' => ChatMessageReport::STATUS_DISMISSED,
            'reviewed_by' => $this->admin->id,
        ]);
        $this->assertDatabaseMissing('chat_messages', [
            'id' => $this->message->id,
        ]);
        $this->assertDatabaseHas('chat_room_users', [
            'room_id' => $this->room->id,
            'user_id' => $this->admin->id,
            'is_muted' => true,
            'muted_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_escalate_report()
    {
        $report = ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_MISINFORMATION,
            'reason' => 'Needs escalation',
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/chat/reports/{$report->id}/review", [
            'action' => 'escalate',
            'notes' => 'Escalated to moderators',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('action', 'escalate');

        $this->assertDatabaseHas('chat_message_reports', [
            'id' => $report->id,
            'status' => ChatMessageReport::STATUS_REVIEWED,
            'reviewed_by' => $this->admin->id,
            'review_notes' => 'Escalated to moderators',
        ]);
    }

    public function test_non_admin_cannot_view_global_report_stats()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/chat/reports/stats');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You are not authorized to view global report stats',
            ]);
    }

    public function test_admin_can_view_room_specific_report_stats()
    {
        ChatMessageReport::create([
            'message_id' => $this->message->id,
            'reported_by' => $this->user->id,
            'room_id' => $this->room->id,
            'report_type' => ChatMessageReport::TYPE_SPAM,
            'reason' => 'Room-specific stats',
            'status' => ChatMessageReport::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/chat/reports/stats?room_id={$this->room->id}&days=30");

        $response->assertStatus(200);
        $response->assertJsonPath('period_days', '30');
        $response->assertJsonPath('total_reports', 1);
        $response->assertJsonPath('report_types.spam', 1);
    }

    public function test_invalid_report_type_is_rejected()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => 'not_a_valid_type',
            'reason' => 'Invalid payload',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['report_type']);
    }

    public function test_auto_moderation_on_multiple_reports()
    {
        // Create 3 reports for the same message to trigger auto-moderation
        for ($i = 0; $i < 3; $i++) {
            $reporter = User::factory()->create();
            ChatMessageReport::create([
                'message_id' => $this->message->id,
                'reported_by' => $reporter->id,
                'room_id' => $this->room->id,
                'report_type' => ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
                'reason' => "Report #{$i}",
                'status' => ChatMessageReport::STATUS_PENDING,
            ]);
        }

        Sanctum::actingAs($this->user);

        // This should trigger auto-moderation
        $response = $this->postJson("/api/chat/reports/rooms/{$this->room->id}/messages/{$this->message->id}", [
            'report_type' => ChatMessageReport::TYPE_HARASSMENT,
            'reason' => 'Final report that triggers auto-moderation',
        ]);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201);

        // Check that the message was deleted
        $this->assertDatabaseMissing('chat_messages', [
            'id' => $this->message->id,
        ]);

        $this->assertEquals(4, ChatMessageReport::where('room_id', $this->room->id)
            ->where('status', ChatMessageReport::STATUS_RESOLVED)
            ->where('message_id', $this->message->id)
            ->count());

        $this->assertDatabaseHas('chat_moderation_actions', [
            'room_id' => $this->room->id,
            'moderator_id' => 1,
            'target_user_id' => $this->admin->id,
            'action_type' => ChatModerationAction::ACTION_DELETE_MESSAGE,
            'reason' => 'Automatic deletion due to multiple reports',
        ]);
    }
}
