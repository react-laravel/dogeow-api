<?php

namespace Tests\Unit\Requests\Note;

use App\Http\Requests\Note\NoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteRequestTest extends TestCase
{
    use RefreshDatabase;

    private NoteRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new NoteRequest;
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayHasKey('content', $rules);
        $this->assertArrayHasKey('content_markdown', $rules);
        $this->assertArrayHasKey('is_draft', $rules);
    }

    public function test_title_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['title']);
        $this->assertStringContainsString('string', $rules['title']);
        $this->assertStringContainsString('max:255', $rules['title']);
    }

    public function test_content_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['content']);
        $this->assertStringContainsString('string', $rules['content']);
    }

    public function test_content_markdown_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['content_markdown']);
        $this->assertStringContainsString('string', $rules['content_markdown']);
    }

    public function test_is_draft_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['is_draft']);
        $this->assertStringContainsString('boolean', $rules['is_draft']);
    }

    public function test_attributes_contain_custom_names()
    {
        $attributes = $this->request->attributes();

        $this->assertArrayHasKey('title', $attributes);
        $this->assertArrayHasKey('content', $attributes);
        $this->assertArrayHasKey('content_markdown', $attributes);
        $this->assertArrayHasKey('is_draft', $attributes);
    }

    public function test_title_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('笔记标题', $attributes['title']);
    }

    public function test_content_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('笔记内容', $attributes['content']);
    }

    public function test_content_markdown_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('笔记 Markdown 内容', $attributes['content_markdown']);
    }

    public function test_is_draft_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('草稿状态', $attributes['is_draft']);
    }
}
