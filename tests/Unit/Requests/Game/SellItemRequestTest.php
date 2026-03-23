<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\SellItemRequest;
use Tests\TestCase;

class SellItemRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new SellItemRequest;

        $this->assertTrue($request->authorize());
    }

    public function test_rules_match_expected_contract(): void
    {
        $request = new SellItemRequest;

        $this->assertSame([
            'item_id' => 'required|integer|min:1|exists:game_items,id',
            'quantity' => 'sometimes|integer|min:1',
        ], $request->rules());
    }

    public function test_messages_match_expected_contract(): void
    {
        $request = new SellItemRequest;
        $messages = $request->messages();

        $this->assertSame('物品 ID 不能为空', $messages['item_id.required']);
        $this->assertSame('物品 ID 必须大于 0', $messages['item_id.min']);
        $this->assertSame('物品不存在', $messages['item_id.exists']);
        $this->assertSame('数量不能小于 1', $messages['quantity.min']);
    }
}
