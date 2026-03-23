<?php

namespace Tests\Feature\Controllers;

use App\Models\Note\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class NoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    public function test_index_returns_user_notes()
    {
        // 创建用户的笔记
        $userNote = Note::factory()->create(['user_id' => $this->user->id]);
        $otherUserNote = Note::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->getJson('/api/notes');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $userNote->id])
            ->assertJsonMissing(['id' => $otherUserNote->id]);
    }

    public function test_store_creates_new_note()
    {
        $data = [
            'title' => 'Test Note',
            'content' => 'Test content',
            'is_draft' => false,
        ];

        $response = $this->postJson('/api/notes', $data);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'Test Note',
                'content' => 'Test content',
                'is_draft' => false,
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('notes', [
            'title' => 'Test Note',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_creates_note_with_markdown()
    {
        $data = [
            'title' => 'Test Note',
            'content' => 'Test content',
            'content_markdown' => '# Test Note\n\nThis is **bold** text.',
            'is_draft' => true,
        ];

        $response = $this->postJson('/api/notes', $data);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'Test Note',
                'content' => 'Test content',
                'content_markdown' => '# Test Note\n\nThis is **bold** text.',
                'is_draft' => true,
            ]);
    }

    public function test_store_uses_content_as_markdown_when_markdown_not_provided()
    {
        $data = [
            'title' => 'Test Note',
            'content' => 'Test content',
        ];

        $response = $this->postJson('/api/notes', $data);

        $response->assertStatus(201)
            ->assertJson([
                'content_markdown' => 'Test content',
            ]);
    }

    public function test_show_returns_note()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/notes/{$note->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $note->id,
                'title' => $note->title,
            ]);
    }

    public function test_show_returns_404_for_other_user_note()
    {
        $otherUserNote = Note::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->getJson("/api/notes/{$otherUserNote->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_note()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'is_draft' => true,
        ];

        $response = $this->putJson("/api/notes/{$note->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Updated Title',
                'content' => 'Updated content',
                'is_draft' => true,
            ]);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_update_partial_fields()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'title' => 'Updated Title',
        ];

        $response = $this->putJson("/api/notes/{$note->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Updated Title',
            ]);

        // 其他字段应该保持不变
        $this->assertEquals($note->content, $response->json('content'));
    }

    public function test_update_returns_404_for_other_user_note()
    {
        $otherUserNote = Note::factory()->create(['user_id' => User::factory()->create()->id]);

        $data = ['title' => 'Updated Title'];

        $response = $this->putJson("/api/notes/{$otherUserNote->id}", $data);

        $response->assertStatus(404);
    }

    public function test_update_with_content_and_markdown()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'content' => 'Updated content',
            'content_markdown' => '# Updated Title\n\nUpdated **content**.',
        ];

        $response = $this->putJson("/api/notes/{$note->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'content' => 'Updated content',
                'content_markdown' => '# Updated Title\n\nUpdated **content**.',
            ]);
    }

    public function test_update_uses_content_as_markdown_when_markdown_not_provided()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'content' => 'Updated content',
        ];

        $response = $this->putJson("/api/notes/{$note->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'content_markdown' => 'Updated content',
            ]);
    }

    public function test_destroy_deletes_note()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/notes/{$note->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }

    public function test_destroy_returns_404_for_other_user_note()
    {
        $otherUserNote = Note::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->deleteJson("/api/notes/{$otherUserNote->id}");

        $response->assertStatus(404);
    }

    public function test_store_validation_fails_without_title()
    {
        $data = [
            'content' => 'Test content',
        ];

        $response = $this->postJson('/api/notes', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_validation_fails_with_long_title()
    {
        $data = [
            'title' => str_repeat('a', 256), // 超过 255 字符
            'content' => 'Test content',
        ];

        $response = $this->postJson('/api/notes', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_validation_fails_with_long_title()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'title' => str_repeat('a', 256), // 超过 255 字符
        ];

        $response = $this->putJson("/api/notes/{$note->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_converts_null_content_to_empty_string()
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'original content',
            'content_markdown' => 'original markdown',
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'content' => null,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'content' => '',
            ]);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'content' => '',
        ]);
    }

    public function test_update_wiki_note_regenerates_slug_when_title_changes_without_slug()
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
            'is_wiki' => true,
            'title' => 'Old Wiki Title',
            'slug' => 'old-wiki-title',
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => 'New Wiki Title',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'New Wiki Title',
                'is_wiki' => true,
            ]);

        $updated = $note->fresh();
        $this->assertNotNull($updated);
        $this->assertNotSame('old-wiki-title', $updated->slug);
        $this->assertStringContainsString('new-wiki-title', (string) $updated->slug);
    }
}
