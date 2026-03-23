<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\UpdatePotionSettingsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdatePotionSettingsRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new UpdatePotionSettingsRequest;

        $this->assertTrue($request->authorize());
    }

    public function test_rules_require_threshold_when_auto_use_enabled(): void
    {
        $request = new UpdatePotionSettingsRequest;

        $this->assertSame([
            'auto_use_hp_potion' => 'nullable|boolean',
            'hp_potion_threshold' => 'nullable|integer|min:1|max:100|required_if_accepted:auto_use_hp_potion',
            'auto_use_mp_potion' => 'nullable|boolean',
            'mp_potion_threshold' => 'nullable|integer|min:1|max:100|required_if_accepted:auto_use_mp_potion',
        ], $request->rules());
    }

    public function test_validation_fails_when_auto_hp_enabled_without_threshold(): void
    {
        $request = new UpdatePotionSettingsRequest;

        $validator = Validator::make(
            ['auto_use_hp_potion' => true],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            '启用自动 HP 药水时必须设置 HP 药水阈值',
            $validator->errors()->first('hp_potion_threshold')
        );
    }

    public function test_validation_passes_when_auto_hp_disabled_without_threshold(): void
    {
        $request = new UpdatePotionSettingsRequest;

        $validator = Validator::make(
            ['auto_use_hp_potion' => false],
            $request->rules(),
            $request->messages()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_messages_include_mp_required_message(): void
    {
        $request = new UpdatePotionSettingsRequest;
        $messages = $request->messages();

        $this->assertSame('启用自动 MP 药水时必须设置 MP 药水阈值', $messages['mp_potion_threshold.required_if_accepted']);
    }
}
