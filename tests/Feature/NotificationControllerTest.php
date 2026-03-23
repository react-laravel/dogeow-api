<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WebPushSummaryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function actingAsUser(): void
    {
        Sanctum::actingAs($this->user);
    }

    public function test_unread_returns_count_and_items(): void
    {
        $this->actingAsUser();

        $notification = $this->user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\Notification',
            'data' => ['title' => 'Test'],
        ]);

        $response = $this->getJson('/api/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $notification->id)
            ->assertJsonPath('data.items.0.type', 'Test\Notification')
            ->assertJsonPath('data.items.0.data.title', 'Test')
            ->assertJsonStructure(['success', 'message', 'data' => ['items' => [['id', 'type', 'data', 'created_at']]]]);
    }

    public function test_unread_returns_empty_when_no_notifications(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.items', []);
    }

    public function test_unread_excludes_read_notifications(): void
    {
        $this->actingAsUser();

        $this->user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\Notification',
            'data' => [],
            'read_at' => now(),
        ]);
        $unread = $this->user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\Unread',
            'data' => ['key' => 'value'],
        ]);

        $response = $this->getJson('/api/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.items.0.id', $unread->id);
    }

    public function test_unread_sends_summary_push_when_user_has_subscription(): void
    {
        Notification::fake();
        Cache::flush();
        $this->actingAsUser();

        DB::table('push_subscriptions')->insert([
            'subscribable_type' => User::class,
            'subscribable_id' => $this->user->id,
            'endpoint' => 'https://example.com/push/' . Str::uuid(),
            'public_key' => 'public',
            'auth_token' => 'auth',
            'content_encoding' => 'aesgcm',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\\Unread',
            'data' => ['title' => 'Unread'],
        ]);

        $response = $this->getJson('/api/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 1);

        Notification::assertSentTo($this->user, WebPushSummaryNotification::class);
        $this->assertNotNull(Cache::get("user:{$this->user->id}:unread_summary_push_at"));
    }

    public function test_unread_respects_summary_push_cooldown(): void
    {
        Notification::fake();
        $this->actingAsUser();

        DB::table('push_subscriptions')->insert([
            'subscribable_type' => User::class,
            'subscribable_id' => $this->user->id,
            'endpoint' => 'https://example.com/push/' . Str::uuid(),
            'public_key' => 'public',
            'auth_token' => 'auth',
            'content_encoding' => 'aesgcm',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::put("user:{$this->user->id}:unread_summary_push_at", now(), now()->addMinutes(5));

        $this->user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\\Unread',
            'data' => ['title' => 'Unread'],
        ]);

        $response = $this->getJson('/api/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 1);

        Notification::assertNothingSent();
    }

    public function test_mark_as_read_marks_single_notification(): void
    {
        $this->actingAsUser();

        $notification = $this->user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\Notification',
            'data' => [],
        ]);

        $response = $this->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('message', '已标记为已读')
            ->assertJsonPath('success', true);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_as_read_returns_404_for_others_notification(): void
    {
        $this->actingAsUser();

        $otherUser = User::factory()->create();
        $notification = $otherUser->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\Notification',
            'data' => [],
        ]);

        $response = $this->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(404);
    }

    public function test_mark_all_as_read_marks_all_unread(): void
    {
        $this->actingAsUser();

        $this->user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\A',
            'data' => [],
        ]);
        $this->user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'Test\B',
            'data' => [],
        ]);

        $response = $this->postJson('/api/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('message', '已全部标记为已读')
            ->assertJsonPath('success', true);

        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    public function test_unread_requires_authentication(): void
    {
        $response = $this->getJson('/api/notifications/unread');

        $response->assertStatus(401);
    }
}
