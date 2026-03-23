<?php

namespace Tests\Feature;

use App\Models\Note\Note;
use App\Models\Note\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoteTagControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_index_returns_user_tags(): void
    {
        // Create tags for the authenticated user
        $userTags = NoteTag::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Create tags for another user (should not be returned)
        $otherUser = User::factory()->create();
        NoteTag::factory()->count(2)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/notes/tags');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'color',
                    'user_id',
                    'created_at',
                    'updated_at',
                ],
            ]);

        // Verify only user's tags are returned
        $responseData = $response->json();
        foreach ($responseData as $tag) {
            $this->assertEquals($this->user->id, $tag['user_id']);
        }
    }

    public function test_index_returns_empty_array_when_no_tags(): void
    {
        $response = $this->getJson('/api/notes/tags');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    public function test_store_creates_new_tag(): void
    {
        $tagData = [
            'name' => 'Test Tag',
            'color' => '#ff0000',
        ];

        $response = $this->postJson('/api/notes/tags', $tagData);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Tag',
                'color' => '#ff0000',
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('note_tags', [
            'name' => 'Test Tag',
            'color' => '#ff0000',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_creates_tag_with_default_color(): void
    {
        $tagData = [
            'name' => 'Test Tag',
        ];

        $response = $this->postJson('/api/notes/tags', $tagData);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Tag',
                'color' => '#3b82f6',
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('note_tags', [
            'name' => 'Test Tag',
            'color' => '#3b82f6',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/notes/tags', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_name_max_length(): void
    {
        $tagData = [
            'name' => str_repeat('a', 51),
        ];

        $response = $this->postJson('/api/notes/tags', $tagData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_name_uniqueness_per_user(): void
    {
        // Create a tag with the same name for the same user
        NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Tag',
        ]);

        $tagData = [
            'name' => 'Test Tag',
        ];

        $response = $this->postJson('/api/notes/tags', $tagData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_allows_same_name_for_different_users(): void
    {
        // Create a tag with the same name for a different user
        $otherUser = User::factory()->create();
        NoteTag::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Test Tag',
        ]);

        $tagData = [
            'name' => 'Test Tag',
        ];

        $response = $this->postJson('/api/notes/tags', $tagData);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Tag',
                'user_id' => $this->user->id,
            ]);
    }

    public function test_store_validates_color_format(): void
    {
        $tagData = [
            'name' => 'Test Tag',
            'color' => 'invalid-color',
        ];

        $response = $this->postJson('/api/notes/tags', $tagData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_store_validates_color_hex_format(): void
    {
        $tagData = [
            'name' => 'Test Tag',
            'color' => '#gggggg',
        ];

        $response = $this->postJson('/api/notes/tags', $tagData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_show_returns_tag(): void
    {
        $tag = NoteTag::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'user_id' => $this->user->id,
            ]);
    }

    public function test_show_returns_404_for_other_user_tag(): void
    {
        $otherUser = User::factory()->create();
        $tag = NoteTag::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_tag(): void
    {
        $response = $this->getJson('/api/notes/tags/999');

        $response->assertStatus(404);
    }

    public function test_update_modifies_tag(): void
    {
        $tag = NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Old Name',
            'color' => '#ff0000',
        ]);

        $updateData = [
            'name' => 'Updated Tag',
            'color' => '#00ff00',
        ];

        $response = $this->putJson("/api/notes/tags/{$tag->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => 'Updated Tag',
                'color' => '#00ff00',
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('note_tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
            'color' => '#00ff00',
        ]);
    }

    public function test_update_partial_fields(): void
    {
        $tag = NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
            'color' => '#ff0000',
        ]);

        $updateData = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/notes/tags/{$tag->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => 'Updated Name',
                'color' => '#ff0000', // Should remain unchanged
            ]);
    }

    public function test_update_validates_name_uniqueness_per_user(): void
    {
        // Create two tags for the same user
        $tag1 = NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Tag 1',
        ]);

        $tag2 = NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Tag 2',
        ]);

        // Try to update tag2 with the same name as tag1
        $updateData = [
            'name' => 'Tag 1',
        ];

        $response = $this->putJson("/api/notes/tags/{$tag2->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_allows_same_name_for_different_users(): void
    {
        // Create tags with the same name for different users
        $otherUser = User::factory()->create();
        $otherTag = NoteTag::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Shared Name',
        ]);

        $tag = NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'My Tag',
        ]);

        $updateData = [
            'name' => 'Shared Name',
        ];

        $response = $this->putJson("/api/notes/tags/{$tag->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => 'Shared Name',
                'user_id' => $this->user->id,
            ]);
    }

    public function test_update_returns_404_for_other_user_tag(): void
    {
        $otherUser = User::factory()->create();
        $tag = NoteTag::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $updateData = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/notes/tags/{$tag->id}", $updateData);

        $response->assertStatus(404);
    }

    public function test_update_returns_404_for_nonexistent_tag(): void
    {
        $updateData = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson('/api/notes/tags/999', $updateData);

        $response->assertStatus(404);
    }

    public function test_destroy_deletes_tag(): void
    {
        $tag = NoteTag::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('note_tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_destroy_deletes_tag_with_notes_relationships(): void
    {
        $tag = NoteTag::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Attach tag to note
        $note->tags()->attach($tag->id);

        $response = $this->deleteJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(204);

        // Tag should be deleted
        $this->assertSoftDeleted('note_tags', [
            'id' => $tag->id,
        ]);

        // Note should still exist
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
        ]);

        // Relationship should be removed
        $this->assertDatabaseMissing('note_note_tag', [
            'note_id' => $note->id,
            'note_tag_id' => $tag->id,
        ]);
    }

    public function test_destroy_returns_404_for_other_user_tag(): void
    {
        $otherUser = User::factory()->create();
        $tag = NoteTag::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/notes/tags/{$tag->id}");

        $response->assertStatus(404);

        // Tag should still exist
        $this->assertDatabaseHas('note_tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_destroy_returns_404_for_nonexistent_tag(): void
    {
        $response = $this->deleteJson('/api/notes/tags/999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_endpoints(): void
    {
        $this->actingAsGuest('sanctum');

        // Create a user and tag without authentication
        $user = User::factory()->create();
        $tag = NoteTag::factory()->create([
            'user_id' => $user->id,
        ]);

        // Test index
        $response = $this->getJson('/api/notes/tags');
        $response->assertStatus(401);

        // Test store
        $response = $this->postJson('/api/notes/tags', ['name' => 'Test Tag']);
        $response->assertStatus(401);

        // Test show
        $response = $this->getJson("/api/notes/tags/{$tag->id}");
        $response->assertStatus(401);

        // Test update
        $response = $this->putJson("/api/notes/tags/{$tag->id}", ['name' => 'Updated Tag']);
        $response->assertStatus(401);

        // Test destroy
        $response = $this->deleteJson("/api/notes/tags/{$tag->id}");
        $response->assertStatus(401);
    }
}
