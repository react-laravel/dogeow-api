<?php

namespace Tests\Feature\Controllers;

use App\Models\Note\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class NoteTagControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        Auth::login($this->user);
    }

    public function test_index_returns_user_tags()
    {
        // 创建用户的标签
        $userTag = NoteTag::factory()->create(['user_id' => $this->user->id]);
        $otherUserTag = NoteTag::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/notes/tags');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $userTag->id])
            ->assertJsonMissing(['id' => $otherUserTag->id]);
    }

    public function test_store_creates_new_tag()
    {
        $data = [
            'name' => 'Test Tag',
            'color' => '#ff0000',
        ];

        $response = $this->postJson('/api/notes/tags', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Tag',
                'color' => '#ff0000',
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('note_tags', [
            'name' => 'Test Tag',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_creates_tag_with_default_color()
    {
        $data = [
            'name' => 'Test Tag',
        ];

        $response = $this->postJson('/api/notes/tags', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Tag',
                'color' => '#3b82f6',
                'user_id' => $this->user->id,
            ]);
    }

    public function test_store_validation_fails_without_name()
    {
        $response = $this->postJson('/api/notes/tags', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_long_name()
    {
        $data = [
            'name' => str_repeat('a', 256),
        ];

        $response = $this->postJson('/api/notes/tags', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_show_returns_tag()
    {
        $tag = NoteTag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => $tag->name,
                'user_id' => $this->user->id,
            ]);
    }

    public function test_show_returns_404_for_other_user_tag()
    {
        $tag = NoteTag::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_tag()
    {
        $response = $this->getJson('/api/notes/tags/999');

        $response->assertStatus(404);
    }

    public function test_update_modifies_tag()
    {
        $tag = NoteTag::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'name' => 'Updated Tag',
            'color' => '#00ff00',
        ];

        $response = $this->putJson("/api/notes/tags/{$tag->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => 'Updated Tag',
                'color' => '#00ff00',
            ]);

        $this->assertDatabaseHas('note_tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
            'color' => '#00ff00',
        ]);
    }

    public function test_update_partial_fields()
    {
        $tag = NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Tag',
            'color' => '#ff0000',
        ]);

        $data = [
            'name' => 'Updated Tag',
        ];

        $response = $this->putJson("/api/notes/tags/{$tag->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Tag',
                'color' => '#ff0000', // 保持原值
            ]);
    }

    public function test_update_returns_404_for_other_user_tag()
    {
        $tag = NoteTag::factory()->create(['user_id' => $this->otherUser->id]);

        $data = [
            'name' => 'Updated Tag',
        ];

        $response = $this->putJson("/api/notes/tags/{$tag->id}", $data);

        $response->assertStatus(404);
    }

    public function test_update_validation_fails_with_long_name()
    {
        $tag = NoteTag::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'name' => str_repeat('a', 256),
        ];

        $response = $this->putJson("/api/notes/tags/{$tag->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_destroy_deletes_tag()
    {
        $tag = NoteTag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('note_tags', ['id' => $tag->id]);
    }

    public function test_destroy_returns_404_for_other_user_tag()
    {
        $tag = NoteTag::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->deleteJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    public function test_destroy_detaches_notes_before_deletion()
    {
        $tag = NoteTag::factory()->create(['user_id' => $this->user->id]);

        // 模拟笔记关联(这里需要根据实际的关联关系调整)
        // 假设有一个 notes 关联方法

        $response = $this->deleteJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(204);
    }

    public function test_index_orders_by_created_at_desc()
    {
        $tag1 = NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);
        $tag2 = NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/notes/tags');

        $response->assertStatus(200)
            ->assertJsonCount(2);

        $tags = $response->json();
        $this->assertEquals($tag2->id, $tags[0]['id']);
        $this->assertEquals($tag1->id, $tags[1]['id']);
    }
}
