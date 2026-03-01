<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('count', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $notification->id)
            ->assertJsonPath('items.0.type', 'Test\Notification')
            ->assertJsonPath('items.0.data.title', 'Test')
            ->assertJsonStructure(['items' => [['id', 'type', 'data', 'created_at']]]);
    }

    public function test_unread_returns_empty_when_no_notifications(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('items', []);
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
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.id', $unread->id);
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
            ->assertJsonPath('message', '已标记为已读');

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
            ->assertJsonPath('message', '已全部标记为已读');

        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    public function test_unread_requires_authentication(): void
    {
        $response = $this->getJson('/api/notifications/unread');

        $response->assertStatus(401);
    }
}
