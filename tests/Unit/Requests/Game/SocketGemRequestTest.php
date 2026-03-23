<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\SocketGemRequest;
use Tests\TestCase;

class SocketGemRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new SocketGemRequest;

        $this->assertTrue($request->authorize());
    }

    public function test_rules_match_expected_contract(): void
    {
        $request = new SocketGemRequest;

        $this->assertSame([
            'item_id' => 'required|integer|min:1|exists:game_items,id',
            'gem_item_id' => 'required|integer|min:1|exists:game_items,id',
            'socket_index' => 'required|integer|min:0',
        ], $request->rules());
    }

    public function test_messages_match_expected_contract(): void
    {
        $request = new SocketGemRequest;
        $messages = $request->messages();

        $this->assertSame('装备 ID 不能为空', $messages['item_id.required']);
        $this->assertSame('装备 ID 必须大于 0', $messages['item_id.min']);
        $this->assertSame('装备不存在', $messages['item_id.exists']);
        $this->assertSame('宝石 ID 不能为空', $messages['gem_item_id.required']);
        $this->assertSame('宝石 ID 必须大于 0', $messages['gem_item_id.min']);
        $this->assertSame('宝石不存在', $messages['gem_item_id.exists']);
        $this->assertSame('插槽索引不能为空', $messages['socket_index.required']);
        $this->assertSame('插槽索引无效', $messages['socket_index.min']);
    }
}
