<?php

namespace Tests\Unit\Requests\Thing;

use App\Http\Requests\Thing\AddItemRelationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddItemRelationRequestTest extends TestCase
{
    use RefreshDatabase;

    private AddItemRelationRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new AddItemRelationRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_contain_relation_fields(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('related_item_id', $rules);
        $this->assertArrayHasKey('relation_type', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertStringContainsString('required', $rules['related_item_id']);
        $this->assertStringContainsString('exists:thing_items,id', $rules['related_item_id']);
        $this->assertStringContainsString('nullable', $rules['description']);
        $this->assertStringContainsString('max:500', $rules['description']);
    }

    public function test_relation_type_rule_uses_allowed_values(): void
    {
        $rule = $this->request->rules()['relation_type'];

        $this->assertIsArray($rule);
        $this->assertCount(3, $rule);
        $this->assertSame('required', $rule[0]);
        $this->assertSame('string', $rule[1]);
    }

    public function test_messages_contain_custom_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertSame('关联物品不能为空', $messages['related_item_id.required']);
        $this->assertSame('关联物品不存在', $messages['related_item_id.exists']);
        $this->assertSame('关联类型不能为空', $messages['relation_type.required']);
        $this->assertSame('关联描述不能超过 500 个字符', $messages['description.max']);
    }
}
