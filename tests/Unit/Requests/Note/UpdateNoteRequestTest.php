<?php

namespace Tests\Unit\Requests\Note;

use App\Http\Requests\Note\UpdateNoteRequest;
use Tests\TestCase;

class UpdateNoteRequestTest extends TestCase
{
    private UpdateNoteRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new UpdateNoteRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_title_is_optional_but_required_when_present(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('title', $rules);
        $this->assertStringContainsString('sometimes', $rules['title']);
        $this->assertStringContainsString('required', $rules['title']);
    }

    public function test_content_is_optional(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('content', $rules);
        $this->assertStringContainsString('sometimes', $rules['content']);
        $this->assertStringContainsString('nullable', $rules['content']);
    }

    public function test_content_markdown_is_optional(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('content_markdown', $rules);
        $this->assertStringContainsString('sometimes', $rules['content_markdown']);
    }

    public function test_is_draft_is_boolean(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('is_draft', $rules);
        $this->assertStringContainsString('sometimes', $rules['is_draft']);
        $this->assertStringContainsString('boolean', $rules['is_draft']);
    }

    public function test_slug_is_optional(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('slug', $rules);
        $this->assertStringContainsString('sometimes', $rules['slug']);
        $this->assertStringContainsString('nullable', $rules['slug']);
    }

    public function test_summary_is_optional(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('summary', $rules);
        $this->assertStringContainsString('sometimes', $rules['summary']);
        $this->assertStringContainsString('nullable', $rules['summary']);
    }

    public function test_is_wiki_is_boolean(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('is_wiki', $rules);
        $this->assertStringContainsString('sometimes', $rules['is_wiki']);
        $this->assertStringContainsString('boolean', $rules['is_wiki']);
    }

    public function test_attributes_contain_chinese_names(): void
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('标题', $attributes['title']);
        $this->assertEquals('内容', $attributes['content']);
        $this->assertEquals('Wiki 节点', $attributes['is_wiki']);
    }
}
