<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\UpdateDifficultyRequest;
use Tests\TestCase;

class UpdateDifficultyRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new UpdateDifficultyRequest;

        $this->assertTrue($request->authorize());
    }

    public function test_rules_include_positive_character_id_and_difficulty_range(): void
    {
        $request = new UpdateDifficultyRequest;

        $this->assertSame([
            'character_id' => 'sometimes|integer|min:1|exists:game_characters,id',
            'difficulty_tier' => 'required|integer|min:0|max:9',
        ], $request->rules());
    }

    public function test_messages_include_character_min_message(): void
    {
        $request = new UpdateDifficultyRequest;
        $messages = $request->messages();

        $this->assertSame('角色 ID 必须大于 0', $messages['character_id.min']);
        $this->assertSame('难度等级不能为空', $messages['difficulty_tier.required']);
        $this->assertSame('难度等级不能小于 0', $messages['difficulty_tier.min']);
        $this->assertSame('难度等级不能大于 9', $messages['difficulty_tier.max']);
    }
}
