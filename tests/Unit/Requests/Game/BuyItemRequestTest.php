<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\BuyItemRequest;
use Tests\TestCase;

class BuyItemRequestTest extends TestCase
{
    private BuyItemRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new BuyItemRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_has_item_id_required(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('item_id', $rules);
        $this->assertStringContainsString('required', $rules['item_id']);
    }

    public function test_rules_item_id_must_exist(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('exists', $rules['item_id']);
    }

    public function test_quantity_is_optional(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('quantity', $rules);
        $this->assertStringContainsString('sometimes', $rules['quantity']);
    }

    public function test_quantity_must_be_at_least_1(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('min:1', $rules['quantity']);
    }

    public function test_messages_in_chinese(): void
    {
        $messages = $this->request->messages();

        $this->assertStringContainsString('物品ID', $messages['item_id.required']);
        $this->assertStringContainsString('数量', $messages['quantity.min']);
    }
}
