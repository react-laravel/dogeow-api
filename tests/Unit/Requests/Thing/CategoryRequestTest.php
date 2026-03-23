<?php

namespace Tests\Unit\Requests\Thing;

use App\Http\Requests\Thing\CategoryRequest;
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
        $this->assertArrayHasKey('parent_id', $rules);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:255', $rules['name']);
    }

    public function test_parent_id_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['parent_id']);
        $this->assertStringContainsString('integer', $rules['parent_id']);
        $this->assertStringContainsString('exists:thing_item_categories,id', $rules['parent_id']);
    }

    public function test_messages_contain_custom_messages()
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('name.max', $messages);
        $this->assertArrayHasKey('parent_id.integer', $messages);
        $this->assertArrayHasKey('parent_id.exists', $messages);
    }

    public function test_name_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('分类名称不能为空', $messages['name.required']);
    }

    public function test_name_max_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('分类名称不能超过 255 个字符', $messages['name.max']);
    }

    public function test_parent_id_integer_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('父分类 ID 必须为整数', $messages['parent_id.integer']);
    }

    public function test_parent_id_exists_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('指定的父分类不存在', $messages['parent_id.exists']);
    }
}
