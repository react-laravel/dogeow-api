<?php

namespace Tests\Unit\Models\Note;

use App\Models\Note\Note;
use App\Models\Note\NoteCategory;
use App\Models\Note\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_can_be_created()
    {
        $user = User::factory()->create();
        $category = NoteCategory::factory()->create();

        $note = Note::factory()->create([
            'user_id' => $user->id,
            'note_category_id' => $category->id,
            'title' => 'Test Note',
            'content' => 'Test content',
            'content_markdown' => '# Test Note',
            'is_draft' => false,
        ]);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Test Note',
            'user_id' => $user->id,
            'note_category_id' => $category->id,
        ]);
    }

    public function test_note_belongs_to_user()
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $note->user);
        $this->assertEquals($user->id, $note->user->id);
    }

    public function test_note_belongs_to_category()
    {
        $category = NoteCategory::factory()->create();
        $note = Note::factory()->create(['note_category_id' => $category->id]);

        $this->assertInstanceOf(NoteCategory::class, $note->category);
        $this->assertEquals($category->id, $note->category->id);
    }

    public function test_note_can_have_tags()
    {
        $note = Note::factory()->create();
        $tag1 = NoteTag::factory()->create();
        $tag2 = NoteTag::factory()->create();

        $note->tags()->attach([$tag1->id, $tag2->id]);

        $this->assertCount(2, $note->tags);
        $this->assertTrue($note->tags->contains($tag1));
        $this->assertTrue($note->tags->contains($tag2));
    }

    public function test_note_can_be_soft_deleted()
    {
        $note = Note::factory()->create();

        $note->delete();

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }

    public function test_note_fillable_attributes()
    {
        $data = [
            'user_id' => User::factory()->create()->id,
            'note_category_id' => NoteCategory::factory()->create()->id,
            'title' => 'Test Note',
            'content' => 'Test content',
            'content_markdown' => '# Test Note',
            'is_draft' => true,
        ];

        $note = Note::create($data);

        $this->assertEquals($data['title'], $note->title);
        $this->assertEquals($data['content'], $note->content);
        $this->assertEquals($data['content_markdown'], $note->content_markdown);
        $this->assertEquals($data['is_draft'], $note->is_draft);
    }

    public function test_note_has_dates_casted()
    {
        $note = Note::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $note->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $note->updated_at);
    }

    public function test_note_can_be_draft()
    {
        $note = Note::factory()->create(['is_draft' => true]);

        $this->assertTrue($note->is_draft);
    }

    public function test_note_can_be_published()
    {
        $note = Note::factory()->create(['is_draft' => false]);

        $this->assertFalse($note->is_draft);
    }

    public function test_note_can_have_markdown_content()
    {
        $markdown = "# Title\n\nThis is **bold** text.";
        $note = Note::factory()->create(['content_markdown' => $markdown]);

        $this->assertEquals($markdown, $note->content_markdown);
    }

    public function test_note_can_have_plain_content()
    {
        $content = 'This is plain text content.';
        $note = Note::factory()->create(['content' => $content]);

        $this->assertEquals($content, $note->content);
    }

    public function test_normalize_slug_converts_to_lowercase()
    {
        $slug = Note::normalizeSlug('Hello World');

        $this->assertEquals('hello-world', $slug);
    }

    public function test_normalize_slug_replaces_spaces_with_hyphens()
    {
        $slug = Note::normalizeSlug('Hello   World   Test');

        $this->assertEquals('hello-world-test', $slug);
    }

    public function test_normalize_slug_handles_chinese_characters()
    {
        $slug = Note::normalizeSlug('你好 世界');

        $this->assertEquals('你好-世界', $slug);
    }

    public function test_normalize_slug_removes_special_characters()
    {
        $slug = Note::normalizeSlug('Hello@#$% World!');

        $this->assertEquals('hello-world', $slug);
    }

    public function test_normalize_slug_returns_original_if_empty()
    {
        $slug = Note::normalizeSlug('!!!');

        $this->assertEquals('!!!', $slug);
    }

    public function test_ensure_unique_slug_returns_slug_when_unique()
    {
        $slug = Note::ensureUniqueSlug('unique-slug');

        $this->assertEquals('unique-slug', $slug);
    }

    public function test_ensure_unique_slug_appends_counter_for_duplicates()
    {
        Note::factory()->create(['slug' => 'test-slug']);

        $slug = Note::ensureUniqueSlug('test-slug');

        $this->assertEquals('test-slug-1', $slug);
    }

    public function test_ensure_unique_slug_increments_counter()
    {
        Note::factory()->create(['slug' => 'test-slug']);
        Note::factory()->create(['slug' => 'test-slug-1']);

        $slug = Note::ensureUniqueSlug('test-slug');

        $this->assertEquals('test-slug-2', $slug);
    }

    public function test_ensure_unique_slug_excludes_specific_id()
    {
        $note = Note::factory()->create(['slug' => 'test-slug']);

        $slug = Note::ensureUniqueSlug('test-slug', $note->id);

        $this->assertEquals('test-slug', $slug);
    }

    public function test_links_from_returns_notes_linked_from_this_note()
    {
        $note = Note::factory()->create();
        $targetNote = Note::factory()->create();

        \App\Models\Note\NoteLink::create([
            'source_id' => $note->id,
            'target_id' => $targetNote->id,
        ]);

        $links = $note->linksFrom;

        $this->assertCount(1, $links);
        $this->assertEquals($targetNote->id, $links->first()->target_id);
    }

    public function test_links_to_returns_notes_linked_to_this_note()
    {
        $note = Note::factory()->create();
        $sourceNote = Note::factory()->create();

        \App\Models\Note\NoteLink::create([
            'source_id' => $sourceNote->id,
            'target_id' => $note->id,
        ]);

        $links = $note->linksTo;

        $this->assertCount(1, $links);
        $this->assertEquals($sourceNote->id, $links->first()->source_id);
    }

    public function test_links_returns_all_related_links()
    {
        $note = Note::factory()->create();
        $sourceNote = Note::factory()->create();
        $targetNote = Note::factory()->create();

        \App\Models\Note\NoteLink::create([
            'source_id' => $sourceNote->id,
            'target_id' => $note->id,
        ]);
        \App\Models\Note\NoteLink::create([
            'source_id' => $note->id,
            'target_id' => $targetNote->id,
        ]);

        $links = $note->links();

        $this->assertCount(2, $links);
    }

    public function test_links_returns_empty_when_no_links()
    {
        $note = Note::factory()->create();

        $links = $note->links();

        $this->assertCount(0, $links);
    }
}
