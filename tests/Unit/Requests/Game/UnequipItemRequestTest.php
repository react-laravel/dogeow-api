<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\UnequipItemRequest;
use Tests\TestCase;

class UnequipItemRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new UnequipItemRequest;

        $this->assertTrue($request->authorize());
    }

    public function test_rules_match_expected_contract(): void
    {
        $request = new UnequipItemRequest;

        $this->assertSame([
            'slot' => 'required|in:weapon,helmet,armor,gloves,boots,belt,ring,amulet',
        ], $request->rules());
    }

    public function test_messages_match_expected_contract(): void
    {
        $request = new UnequipItemRequest;
        $messages = $request->messages();

        $this->assertSame('装备槽位不能为空', $messages['slot.required']);
        $this->assertSame('装备槽位无效', $messages['slot.in']);
    }
}
