<?php

namespace Tests\Unit\Requests\Nav;

use App\Http\Requests\Nav\CategoryRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryRequestTest extends TestCase
{
    use RefreshDatabase;

    private CategoryRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new CategoryRequest;
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('icon', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayHasKey('sort_order', $rules);
        $this->assertArrayHasKey('is_visible', $rules);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:50', $rules['name']);
    }

    public function test_icon_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['icon']);
        $this->assertStringContainsString('string', $rules['icon']);
        $this->assertStringContainsString('max:100', $rules['icon']);
    }

    public function test_description_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['description']);
        $this->assertStringContainsString('string', $rules['description']);
    }

    public function test_sort_order_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['sort_order']);
        $this->assertStringContainsString('integer', $rules['sort_order']);
    }

    public function test_is_visible_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['is_visible']);
        $this->assertStringContainsString('boolean', $rules['is_visible']);
    }

    public function test_attributes_contain_custom_names()
    {
        $attributes = $this->request->attributes();

        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('icon', $attributes);
        $this->assertArrayHasKey('description', $attributes);
        $this->assertArrayHasKey('sort_order', $attributes);
        $this->assertArrayHasKey('is_visible', $attributes);
    }

    public function test_name_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('分类名称', $attributes['name']);
    }

    public function test_icon_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('分类图标', $attributes['icon']);
    }

    public function test_description_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('分类描述', $attributes['description']);
    }

    public function test_sort_order_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('排序', $attributes['sort_order']);
    }

    public function test_is_visible_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('是否可见', $attributes['is_visible']);
    }
}
