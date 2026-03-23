<?php

namespace Tests\Unit\Requests\Note;

use App\Http\Requests\Note\NoteTagRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTagRequestTest extends TestCase
{
    use RefreshDatabase;

    private NoteTagRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new NoteTagRequest;
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('color', $rules);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertIsArray($rules['name']);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
        $this->assertContains('max:50', $rules['name']);
        // unique 规则是一个 Rule 对象，不是字符串
        $this->assertCount(4, $rules['name']);
    }

    public function test_color_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('sometimes', $rules['color']);
        $this->assertStringContainsString('string', $rules['color']);
        $this->assertStringContainsString('regex:/^#([A-Fa-f0-9]{6})$/', $rules['color']);
    }

    public function test_attributes_contain_custom_names()
    {
        $attributes = $this->request->attributes();

        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('color', $attributes);
    }

    public function test_name_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('标签名称', $attributes['name']);
    }

    public function test_color_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('标签颜色', $attributes['color']);
    }
}
