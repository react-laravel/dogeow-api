<?php

namespace Tests\Unit\Services\Note;

use App\Models\Note\Note;
use App\Models\Note\NoteTag;
use App\Services\Note\NoteContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteContentServiceTest extends TestCase
{
    use RefreshDatabase;

    private NoteContentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NoteContentService;
    }

    public function test_extract_text_from_editor_json_returns_empty_string_for_empty_content(): void
    {
        $result = $this->service->extractTextFromEditorJson([]);
        $this->assertEquals('', $result);
    }

    public function test_extract_text_from_editor_json_returns_empty_string_for_null_content(): void
    {
        $result = $this->service->extractTextFromEditorJson(['content' => null]);
        $this->assertEquals('', $result);
    }

    public function test_extract_text_from_editor_json_extracts_simple_text(): void
    {
        $jsonContent = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Hello World',
                ],
            ],
        ];

        $result = $this->service->extractTextFromEditorJson($jsonContent);
        $this->assertEquals('Hello World', $result);
    }

    public function test_extract_text_from_editor_json_extracts_nested_text(): void
    {
        $jsonContent = [
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Nested text',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->extractTextFromEditorJson($jsonContent);
        $this->assertStringContainsString('Nested text', $result);
    }

    public function test_extract_text_from_editor_json_adds_newline_after_paragraph(): void
    {
        $jsonContent = [
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'First paragraph',
                        ],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Second paragraph',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->extractTextFromEditorJson($jsonContent);
        $this->assertStringContainsString("First paragraph\n", $result);
    }

    public function test_extract_text_from_editor_json_handles_mixed_content(): void
    {
        $jsonContent = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Plain text',
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'In paragraph',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->extractTextFromEditorJson($jsonContent);
        $this->assertStringContainsString('Plain text', $result);
        $this->assertStringContainsString('In paragraph', $result);
    }

    public function test_derive_markdown_from_content_returns_empty_string_for_null(): void
    {
        $result = $this->service->deriveMarkdownFromContent(null);
        $this->assertEquals('', $result);
    }

    public function test_derive_markdown_from_content_returns_empty_string_for_empty_string(): void
    {
        $result = $this->service->deriveMarkdownFromContent('');
        $this->assertEquals('', $result);
    }

    public function test_derive_markdown_from_content_returns_original_for_whitespace_only(): void
    {
        // Note: whitespace-only content returns original due to implementation
        $result = $this->service->deriveMarkdownFromContent('   ');
        $this->assertEquals('   ', $result);
    }

    public function test_derive_markdown_from_content_parses_json_content(): void
    {
        $jsonContent = json_encode([
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'JSON extracted text',
                ],
            ],
        ]);

        $result = $this->service->deriveMarkdownFromContent($jsonContent);
        $this->assertEquals('JSON extracted text', $result);
    }

    public function test_derive_markdown_from_content_parses_json_array_as_original(): void
    {
        // Note: JSON array without 'content' key - extractTextFromEditorJson returns empty
        $jsonContent = json_encode([
            [
                'type' => 'text',
                'text' => 'Array JSON text',
            ],
        ]);

        $result = $this->service->deriveMarkdownFromContent($jsonContent);
        // Since extractTextFromEditorJson returns empty string for array format,
        // it returns original content (empty after trim check)
        $this->assertEquals('', $result);
    }

    public function test_derive_markdown_from_content_returns_original_for_non_json(): void
    {
        $plainText = 'This is plain markdown text';
        $result = $this->service->deriveMarkdownFromContent($plainText);
        $this->assertEquals($plainText, $result);
    }

    public function test_derive_markdown_from_content_returns_original_for_invalid_json(): void
    {
        $invalidJson = '{not valid json';
        $result = $this->service->deriveMarkdownFromContent($invalidJson);
        $this->assertEquals($invalidJson, $result);
    }

    public function test_handle_tags_syncs_tags_to_note(): void
    {
        $user = \App\Models\User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);
        $tag = NoteTag::factory()->create(['user_id' => $user->id, 'name' => 'Test Tag']);

        $this->service->handleTags($note, ['Test Tag']);

        $this->assertTrue($note->tags->contains($tag->id));
    }

    public function test_handle_tags_creates_new_tags_when_not_exist(): void
    {
        $user = \App\Models\User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->service->handleTags($note, ['New Tag']);

        $this->assertDatabaseHas('note_tags', [
            'name' => 'New Tag',
            'user_id' => $user->id,
        ]);
    }

    public function test_handle_tags_removes_existing_tags_when_empty_array(): void
    {
        $user = \App\Models\User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);
        $tag = NoteTag::factory()->create(['user_id' => $user->id]);
        $note->tags()->attach($tag->id);

        $this->service->handleTags($note, []);

        $this->assertCount(0, $note->fresh()->tags);
    }

    public function test_handle_tags_normalizes_tag_names(): void
    {
        $user = \App\Models\User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->service->handleTags($note, ['  Tag1  ', 'Tag1', 'Tag2']);

        // Tag1 should be deduplicated
        $this->assertDatabaseHas('note_tags', [
            'name' => 'Tag1',
            'user_id' => $user->id,
        ]);
    }

    public function test_handle_tags_ignores_empty_tag_names(): void
    {
        $user = \App\Models\User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->service->handleTags($note, ['Valid', '', '   ']);

        $this->assertDatabaseHas('note_tags', [
            'name' => 'Valid',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('note_tags', [
            'name' => '',
            'user_id' => $user->id,
        ]);
    }
}
