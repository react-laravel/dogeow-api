<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\MoveItemRequest;
use Tests\TestCase;

class MoveItemRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new MoveItemRequest;

        $this->assertTrue($request->authorize());
    }

    public function test_rules_match_expected_contract(): void
    {
        $request = new MoveItemRequest;

        $this->assertSame([
            'item_id' => 'required|integer|min:1|exists:game_items,id',
            'to_storage' => 'required|boolean',
            'slot_index' => 'sometimes|integer|min:0',
        ], $request->rules());
    }

    public function test_messages_match_expected_contract(): void
    {
        $request = new MoveItemRequest;
        $messages = $request->messages();

        $this->assertSame('物品 ID 不能为空', $messages['item_id.required']);
        $this->assertSame('物品 ID 必须大于 0', $messages['item_id.min']);
        $this->assertSame('物品不存在', $messages['item_id.exists']);
        $this->assertSame('目标位置不能为空', $messages['to_storage.required']);
    }
}
