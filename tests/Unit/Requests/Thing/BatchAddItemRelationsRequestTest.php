<?php

namespace Tests\Unit\Requests\Thing;

use App\Http\Requests\Thing\BatchAddItemRelationsRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchAddItemRelationsRequestTest extends TestCase
{
    use RefreshDatabase;

    private BatchAddItemRelationsRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new BatchAddItemRelationsRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_contain_batch_relation_fields(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('relations', $rules);
        $this->assertArrayHasKey('relations.*.related_item_id', $rules);
        $this->assertArrayHasKey('relations.*.relation_type', $rules);
        $this->assertArrayHasKey('relations.*.description', $rules);
        $this->assertStringContainsString('required', $rules['relations']);
        $this->assertStringContainsString('array', $rules['relations']);
        $this->assertStringContainsString('min:1', $rules['relations']);
        $this->assertStringContainsString('exists:thing_items,id', $rules['relations.*.related_item_id']);
    }

    public function test_relation_type_rule_uses_allowed_values(): void
    {
        $rule = $this->request->rules()['relations.*.relation_type'];

        $this->assertIsArray($rule);
        $this->assertCount(3, $rule);
        $this->assertSame('required', $rule[0]);
        $this->assertSame('string', $rule[1]);
    }

    public function test_messages_contain_custom_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertSame('关联列表不能为空', $messages['relations.required']);
        $this->assertSame('至少需要提供一个关联', $messages['relations.min']);
        $this->assertSame('关联物品不存在', $messages['relations.*.related_item_id.exists']);
        $this->assertSame('关联描述不能超过 500 个字符', $messages['relations.*.description.max']);
    }
}
