<?php

namespace Tests\Unit\Requests\Note;

use App\Http\Requests\Note\NoteCategoryRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteCategoryRequestTest extends TestCase
{
    use RefreshDatabase;

    private NoteCategoryRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new NoteCategoryRequest;
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('description', $rules);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:50', $rules['name']);
    }

    public function test_description_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['description']);
        $this->assertStringContainsString('string', $rules['description']);
        $this->assertStringContainsString('max:200', $rules['description']);
    }

    public function test_attributes_contain_custom_names()
    {
        $attributes = $this->request->attributes();

        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('description', $attributes);
    }

    public function test_name_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('分类名称', $attributes['name']);
    }

    public function test_description_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('分类描述', $attributes['description']);
    }
}
