<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\DeleteCharacterRequest;
use Tests\TestCase;

class DeleteCharacterRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new DeleteCharacterRequest;

        $this->assertTrue($request->authorize());
    }

    public function test_rules_include_positive_character_id_constraint(): void
    {
        $request = new DeleteCharacterRequest;

        $this->assertSame([
            'character_id' => 'required|integer|min:1|exists:game_characters,id',
        ], $request->rules());
    }

    public function test_messages_include_min_message(): void
    {
        $request = new DeleteCharacterRequest;
        $messages = $request->messages();

        $this->assertSame('角色 ID 不能为空', $messages['character_id.required']);
        $this->assertSame('角色 ID 必须大于 0', $messages['character_id.min']);
        $this->assertSame('角色不存在', $messages['character_id.exists']);
    }
}
