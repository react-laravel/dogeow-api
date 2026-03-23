<?php

namespace Tests\Unit\Requests\Nav;

use App\Http\Requests\Nav\ItemRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemRequestTest extends TestCase
{
    use RefreshDatabase;

    private ItemRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new ItemRequest;
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('nav_category_id', $rules);
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('url', $rules);
        $this->assertArrayHasKey('icon', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayHasKey('sort_order', $rules);
        $this->assertArrayHasKey('is_visible', $rules);
        $this->assertArrayHasKey('is_new_window', $rules);
    }

    public function test_nav_category_id_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['nav_category_id']);
        $this->assertStringContainsString('exists:nav_categories,id', $rules['nav_category_id']);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:50', $rules['name']);
    }

    public function test_url_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['url']);
        $this->assertStringContainsString('string', $rules['url']);
        $this->assertStringContainsString('max:255', $rules['url']);
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

    public function test_is_new_window_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['is_new_window']);
        $this->assertStringContainsString('boolean', $rules['is_new_window']);
    }

    public function test_attributes_contain_custom_names()
    {
        $attributes = $this->request->attributes();

        $this->assertArrayHasKey('nav_category_id', $attributes);
        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('url', $attributes);
        $this->assertArrayHasKey('icon', $attributes);
        $this->assertArrayHasKey('description', $attributes);
        $this->assertArrayHasKey('sort_order', $attributes);
        $this->assertArrayHasKey('is_visible', $attributes);
        $this->assertArrayHasKey('is_new_window', $attributes);
    }

    public function test_nav_category_id_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('分类 ID', $attributes['nav_category_id']);
    }

    public function test_name_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('导航名称', $attributes['name']);
    }

    public function test_url_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('链接地址', $attributes['url']);
    }

    public function test_icon_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('图标', $attributes['icon']);
    }

    public function test_description_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('描述', $attributes['description']);
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

    public function test_is_new_window_attribute()
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('是否新窗口打开', $attributes['is_new_window']);
    }
}
