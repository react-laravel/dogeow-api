<?php

namespace Tests\Feature;

use App\Models\Note\Note;
use App\Models\Note\NoteCategory;
use App\Models\Note\NoteLink;
use App\Models\Note\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_index_returns_user_notes(): void
    {
        // Create notes for the authenticated user
        $userNotes = Note::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Create notes for another user (should not be returned)
        $otherUser = User::factory()->create();
        Note::factory()->count(2)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/notes');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'content',
                    'content_markdown',
                    'is_draft',
                    'user_id',
                    'created_at',
                    'updated_at',
                    'category',
                    'tags',
                ],
            ]);

        // Verify only user's notes are returned
        $responseData = $response->json();
        foreach ($responseData as $note) {
            $this->assertEquals($this->user->id, $note['user_id']);
        }
    }

    public function test_index_returns_notes_ordered_by_updated_at_desc(): void
    {
        // Create notes with different updated_at times
        $oldNote = Note::factory()->create([
            'user_id' => $this->user->id,
            'updated_at' => now()->subDays(2),
        ]);

        $newNote = Note::factory()->create([
            'user_id' => $this->user->id,
            'updated_at' => now(),
        ]);

        $middleNote = Note::factory()->create([
            'user_id' => $this->user->id,
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/notes');

        $response->assertStatus(200);

        $notes = $response->json();
        $this->assertEquals($newNote->id, $notes[0]['id']);
        $this->assertEquals($middleNote->id, $notes[1]['id']);
        $this->assertEquals($oldNote->id, $notes[2]['id']);
    }

    public function test_index_returns_empty_array_when_user_has_no_notes(): void
    {
        $response = $this->getJson('/api/notes');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    public function test_store_creates_new_note(): void
    {
        $noteData = [
            'title' => 'Test Note',
            'content' => 'This is test content',
            'content_markdown' => '# Test Note\n\nThis is test content',
            'is_draft' => false,
        ];

        $response = $this->postJson('/api/notes', $noteData);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'Test Note',
                'content' => 'This is test content',
                'content_markdown' => '# Test Note\n\nThis is test content',
                'is_draft' => false,
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('notes', [
            'title' => 'Test Note',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_creates_note_without_markdown(): void
    {
        $noteData = [
            'title' => 'Test Note',
            'content' => 'This is test content',
            'is_draft' => true,
        ];

        $response = $this->postJson('/api/notes', $noteData);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'Test Note',
                'content' => 'This is test content',
                'content_markdown' => 'This is test content', // Should use content as markdown
                'is_draft' => true,
                'user_id' => $this->user->id,
            ]);
    }

    public function test_store_creates_note_with_empty_content(): void
    {
        $noteData = [
            'title' => 'Test Note',
            'content' => '',
            'is_draft' => false,
        ];

        $response = $this->postJson('/api/notes', $noteData);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'Test Note',
                'content' => '',
                'content_markdown' => '',
                'is_draft' => false,
                'user_id' => $this->user->id,
            ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/notes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_validates_title_max_length(): void
    {
        $noteData = [
            'title' => str_repeat('a', 256), // Exceeds 255 characters
            'content' => 'Test content',
        ];

        $response = $this->postJson('/api/notes', $noteData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_validates_title_type(): void
    {
        $noteData = [
            'title' => 123, // Should be string
            'content' => 'Test content',
        ];

        $response = $this->postJson('/api/notes', $noteData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_validates_is_draft_boolean(): void
    {
        $noteData = [
            'title' => 'Test Note',
            'content' => 'Test content',
            'is_draft' => 'not_boolean', // Should be boolean
        ];

        $response = $this->postJson('/api/notes', $noteData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_draft']);
    }

    public function test_show_returns_note(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/notes/{$note->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $note->id,
                'title' => $note->title,
                'user_id' => $this->user->id,
            ]);
    }

    public function test_show_returns_note_with_relationships(): void
    {
        $category = NoteCategory::factory()->create();
        $tag = NoteTag::factory()->create();

        $note = Note::factory()->create([
            'user_id' => $this->user->id,
            'note_category_id' => $category->id,
        ]);
        $note->tags()->attach($tag->id);

        $response = $this->getJson("/api/notes/{$note->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'content',
                'content_markdown',
                'is_draft',
                'user_id',
                'category',
                'tags',
            ]);
    }

    public function test_show_returns_404_for_other_user_note(): void
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/notes/{$note->id}");

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_note(): void
    {
        $response = $this->getJson('/api/notes/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_soft_deleted_note(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $note->delete(); // Soft delete

        $response = $this->getJson("/api/notes/{$note->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_note(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'content_markdown' => '# Updated Title\n\nUpdated content',
            'is_draft' => true,
        ];

        $response = $this->putJson("/api/notes/{$note->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $note->id,
                'title' => 'Updated Title',
                'content' => 'Updated content',
                'content_markdown' => '# Updated Title\n\nUpdated content',
                'is_draft' => true,
            ]);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ]);
    }

    public function test_update_partial_fields(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'content' => 'Original content',
            'is_draft' => false,
        ]);

        // Update only title
        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => 'New Title',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'New Title',
                'content' => 'Original content', // Should remain unchanged
                'is_draft' => false, // Should remain unchanged
            ]);
    }

    public function test_update_content_without_markdown(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'content' => 'New content without markdown',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'content' => 'New content without markdown',
                'content_markdown' => 'New content without markdown', // Should use content as markdown
            ]);
    }

    public function test_update_content_with_empty_content(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Original content',
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'content' => '',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'content' => '',
                'content_markdown' => '', // Should be empty when content is empty
            ]);
    }

    public function test_update_validates_title(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => '', // Empty title should fail validation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_validates_title_max_length(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => str_repeat('a', 256), // Exceeds 255 characters
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_validates_is_draft_boolean(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'is_draft' => 'not_boolean',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_draft']);
    }

    public function test_update_returns_404_for_other_user_note(): void
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_returns_404_for_nonexistent_note(): void
    {
        $response = $this->putJson('/api/notes/99999', [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_returns_404_for_soft_deleted_note(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $note->delete(); // Soft delete

        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(404);
    }

    public function test_destroy_deletes_note(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/notes/{$note->id}");

        $response->assertStatus(204);

        // Since Note uses SoftDeletes, we should use assertSoftDeleted
        $this->assertSoftDeleted('notes', [
            'id' => $note->id,
        ]);
    }

    public function test_destroy_returns_404_for_other_user_note(): void
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/notes/{$note->id}");

        $response->assertStatus(404);

        // Note should not be soft deleted since user doesn't own it
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
        ]);
    }

    public function test_destroy_returns_404_for_nonexistent_note(): void
    {
        $response = $this->deleteJson('/api/notes/99999');

        $response->assertStatus(404);
    }

    public function test_destroy_returns_404_for_already_deleted_note(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $note->delete(); // Soft delete first

        $response = $this->deleteJson("/api/notes/{$note->id}");

        $response->assertStatus(404);
    }

    public function test_index_with_graph_view_returns_filtered_nodes_and_links(): void
    {
        $userTag = NoteTag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'knowledge',
        ]);

        $userNote = Note::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'My Note',
            'slug' => 'my-note',
            'summary' => 'User summary',
        ]);
        $userNote->tags()->attach($userTag);

        $wikiNote = Note::factory()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Wiki Note',
            'slug' => 'wiki-note',
            'summary' => 'Wiki summary',
            'is_wiki' => true,
        ]);

        $otherPrivateNote = Note::factory()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Hidden Note',
            'slug' => 'hidden-note',
            'is_wiki' => false,
        ]);

        $deletedNote = Note::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Deleted Node',
            'slug' => 'deleted-node',
        ]);

        NoteLink::create([
            'source_id' => $userNote->id,
            'target_id' => $wikiNote->id,
            'type' => 'related',
        ]);

        NoteLink::create([
            'source_id' => $userNote->id,
            'target_id' => $deletedNote->id,
            'type' => 'stale',
        ]);

        $deletedNote->delete();

        $response = $this->getJson('/api/notes?view=graph');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Graph retrieved successfully')
            ->assertJsonCount(2, 'nodes')
            ->assertJsonCount(1, 'links')
            ->assertJsonPath('nodes.0.tags.0', 'knowledge')
            ->assertJsonPath('links.0.type', 'related');

        $nodeIds = collect($response->json('nodes'))->pluck('id')->all();
        $this->assertContains($userNote->id, $nodeIds);
        $this->assertContains($wikiNote->id, $nodeIds);
        $this->assertNotContains($otherPrivateNote->id, $nodeIds);
    }

    public function test_get_article_by_slug_returns_article_content(): void
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Slug Article',
            'slug' => 'slug-article',
            'content' => '<p>Rendered HTML</p>',
            'content_markdown' => '# Slug Article',
        ]);

        $response = $this->getJson("/api/notes/article/{$note->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Article retrieved successfully',
                'title' => 'Slug Article',
                'slug' => 'slug-article',
                'content' => '<p>Rendered HTML</p>',
                'content_markdown' => '# Slug Article',
                'html' => '<p>Rendered HTML</p>',
            ]);
    }

    public function test_get_article_by_slug_returns_404_when_missing(): void
    {
        $response = $this->getJson('/api/notes/article/missing-article');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Article not found',
            ]);
    }

    public function test_get_all_wiki_articles_returns_only_wiki_notes(): void
    {
        Note::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Wiki One',
            'slug' => 'wiki-one',
            'content' => 'Wiki content',
            'content_markdown' => '# Wiki One',
            'is_wiki' => true,
        ]);

        Note::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Regular Note',
            'slug' => 'regular-note',
            'content' => 'Regular content',
            'content_markdown' => '# Regular Note',
            'is_wiki' => false,
        ]);

        $response = $this->getJson('/api/notes/wiki/articles');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'All wiki articles retrieved successfully')
            ->assertJsonCount(1, 'articles')
            ->assertJsonPath('articles.0.slug', 'wiki-one');
    }

    public function test_store_handles_tags_and_json_content(): void
    {
        $jsonContent = json_encode([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Alpha'],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Beta'],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson('/api/notes', [
            'title' => 'JSON Note',
            'content' => $jsonContent,
            'tags' => [' alpha ', 'beta', 'alpha'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'JSON Note')
            ->assertJsonPath('content_markdown', "Alpha\nBeta");

        $note = Note::findOrFail($response->json('id'));
        $this->assertSame(['alpha', 'beta'], $note->tags()->orderBy('name')->pluck('name')->all());
    }

    public function test_show_allows_access_to_other_users_wiki_note(): void
    {
        $wikiNote = Note::factory()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Shared Wiki',
            'is_wiki' => true,
        ]);

        $response = $this->getJson("/api/notes/{$wikiNote->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $wikiNote->id,
                'title' => 'Shared Wiki',
                'is_wiki' => true,
            ]);
    }

    public function test_update_generates_unique_slug_for_wiki_note(): void
    {
        Note::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Knowledge Base',
            'slug' => 'knowledge-base',
            'is_wiki' => true,
        ]);

        $note = Note::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Draft Note',
            'slug' => null,
            'is_wiki' => false,
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => 'Knowledge Base',
            'is_wiki' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('slug', 'knowledge-base-1')
            ->assertJsonPath('is_wiki', true);
    }

    public function test_update_allows_modifying_other_users_wiki_note(): void
    {
        $wikiNote = Note::factory()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Team Wiki',
            'slug' => 'team-wiki',
            'is_wiki' => true,
            'content' => 'Old content',
            'content_markdown' => 'Old content',
        ]);

        $response = $this->putJson("/api/notes/{$wikiNote->id}", [
            'content' => 'Updated shared wiki',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('content', 'Updated shared wiki')
            ->assertJsonPath('content_markdown', 'Updated shared wiki');
    }

    public function test_store_link_creates_new_link(): void
    {
        $source = Note::factory()->create(['user_id' => $this->user->id]);
        $target = Note::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson('/api/notes/links', [
            'source_id' => $source->id,
            'target_id' => $target->id,
            'type' => 'reference',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Link created successfully')
            ->assertJsonPath('link.source', $source->id)
            ->assertJsonPath('link.target', $target->id)
            ->assertJsonPath('link.type', 'reference');
    }

    public function test_store_link_rejects_duplicate_link(): void
    {
        $source = Note::factory()->create(['user_id' => $this->user->id]);
        $target = Note::factory()->create(['user_id' => $this->user->id]);

        NoteLink::create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'type' => 'reference',
        ]);

        $response = $this->postJson('/api/notes/links', [
            'source_id' => $source->id,
            'target_id' => $target->id,
            'type' => 'reference',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Link already exists',
            ]);
    }

    public function test_store_link_validates_source_and_target_difference(): void
    {
        $source = Note::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson('/api/notes/links', [
            'source_id' => $source->id,
            'target_id' => $source->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_id']);
    }

    public function test_destroy_link_deletes_existing_link(): void
    {
        $source = Note::factory()->create(['user_id' => $this->user->id]);
        $target = Note::factory()->create(['user_id' => $this->user->id]);

        $link = NoteLink::create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'type' => 'reference',
        ]);

        $response = $this->deleteJson("/api/notes/links/{$link->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Link deleted successfully',
            ]);

        $this->assertDatabaseMissing('note_links', [
            'id' => $link->id,
        ]);
    }
}
