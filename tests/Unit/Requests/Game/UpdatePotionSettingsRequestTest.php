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

    public function test_rules_only_require_boolean_toggles(): void
    {
        $request = new UpdatePotionSettingsRequest;

        $this->assertSame([
            'auto_use_hp_potion' => 'nullable|boolean',
            'auto_use_mp_potion' => 'nullable|boolean',
        ], $request->rules());
    }

    public function test_validation_passes_with_only_toggles(): void
    {
        $request = new UpdatePotionSettingsRequest;

        $validator = Validator::make(
            ['auto_use_hp_potion' => true, 'auto_use_mp_potion' => false],
            $request->rules(),
            $request->messages()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_messages_are_empty(): void
    {
        $request = new UpdatePotionSettingsRequest;
        $messages = $request->messages();

        $this->assertSame([], $messages);
    }
}
