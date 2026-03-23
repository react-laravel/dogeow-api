<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\EquipItemRequest;
use App\Http\Requests\Game\MoveItemRequest;
use App\Http\Requests\Game\SellItemRequest;
use App\Http\Requests\Game\SocketGemRequest;
use App\Http\Requests\Game\UnequipItemRequest;
use App\Http\Requests\Game\UnsocketGemRequest;
use Tests\TestCase;

class InventoryActionRequestTest extends TestCase
{
    public function test_equip_item_request_contract(): void
    {
        $request = new EquipItemRequest;

        $this->assertTrue($request->authorize());
        $this->assertSame([
            'item_id' => 'required|integer|min:1|exists:game_items,id',
        ], $request->rules());
        $this->assertSame('物品 ID 不能为空', $request->messages()['item_id.required']);
        $this->assertSame('物品 ID 必须大于 0', $request->messages()['item_id.min']);
        $this->assertSame('物品不存在', $request->messages()['item_id.exists']);
    }

    public function test_move_item_request_contract(): void
    {
        $request = new MoveItemRequest;

        $this->assertTrue($request->authorize());
        $this->assertSame([
            'item_id' => 'required|integer|min:1|exists:game_items,id',
            'to_storage' => 'required|boolean',
            'slot_index' => 'sometimes|integer|min:0',
        ], $request->rules());
        $this->assertSame('物品 ID 不能为空', $request->messages()['item_id.required']);
        $this->assertSame('物品 ID 必须大于 0', $request->messages()['item_id.min']);
        $this->assertSame('物品不存在', $request->messages()['item_id.exists']);
        $this->assertSame('目标位置不能为空', $request->messages()['to_storage.required']);
    }

    public function test_sell_item_request_contract(): void
    {
        $request = new SellItemRequest;

        $this->assertTrue($request->authorize());
        $this->assertSame([
            'item_id' => 'required|integer|min:1|exists:game_items,id',
            'quantity' => 'sometimes|integer|min:1',
        ], $request->rules());
        $this->assertSame('物品 ID 不能为空', $request->messages()['item_id.required']);
        $this->assertSame('物品 ID 必须大于 0', $request->messages()['item_id.min']);
        $this->assertSame('物品不存在', $request->messages()['item_id.exists']);
        $this->assertSame('数量不能小于 1', $request->messages()['quantity.min']);
    }

    public function test_socket_gem_request_contract(): void
    {
        $request = new SocketGemRequest;

        $this->assertTrue($request->authorize());
        $this->assertSame([
            'item_id' => 'required|integer|min:1|exists:game_items,id',
            'gem_item_id' => 'required|integer|min:1|exists:game_items,id',
            'socket_index' => 'required|integer|min:0',
        ], $request->rules());
        $this->assertSame('装备 ID 不能为空', $request->messages()['item_id.required']);
        $this->assertSame('装备 ID 必须大于 0', $request->messages()['item_id.min']);
        $this->assertSame('装备不存在', $request->messages()['item_id.exists']);
        $this->assertSame('宝石 ID 不能为空', $request->messages()['gem_item_id.required']);
        $this->assertSame('宝石 ID 必须大于 0', $request->messages()['gem_item_id.min']);
        $this->assertSame('宝石不存在', $request->messages()['gem_item_id.exists']);
        $this->assertSame('插槽索引不能为空', $request->messages()['socket_index.required']);
        $this->assertSame('插槽索引无效', $request->messages()['socket_index.min']);
    }

    public function test_unequip_item_request_contract(): void
    {
        $request = new UnequipItemRequest;

        $this->assertTrue($request->authorize());
        $this->assertSame([
            'slot' => 'required|in:weapon,helmet,armor,gloves,boots,belt,ring,amulet',
        ], $request->rules());
        $this->assertSame('装备槽位不能为空', $request->messages()['slot.required']);
        $this->assertSame('装备槽位无效', $request->messages()['slot.in']);
    }

    public function test_unsocket_gem_request_contract(): void
    {
        $request = new UnsocketGemRequest;

        $this->assertTrue($request->authorize());
        $this->assertSame([
            'item_id' => 'required|integer|min:1|exists:game_items,id',
            'socket_index' => 'required|integer|min:0',
        ], $request->rules());
        $this->assertSame('装备 ID 不能为空', $request->messages()['item_id.required']);
        $this->assertSame('装备 ID 必须大于 0', $request->messages()['item_id.min']);
        $this->assertSame('装备不存在', $request->messages()['item_id.exists']);
        $this->assertSame('插槽索引不能为空', $request->messages()['socket_index.required']);
        $this->assertSame('插槽索引无效', $request->messages()['socket_index.min']);
    }
}
