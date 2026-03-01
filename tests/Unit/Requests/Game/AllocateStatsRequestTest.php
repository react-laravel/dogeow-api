<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\AllocateStatsRequest;
use Tests\TestCase;

class AllocateStatsRequestTest extends TestCase
{
    private AllocateStatsRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new AllocateStatsRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_returns_array(): void
    {
        $rules = $this->request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('character_id', $rules);
    }

    public function test_character_id_is_required(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['character_id']);
    }

    public function test_character_id_must_be_integer(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('integer', $rules['character_id']);
    }

    public function test_stat_attributes_are_optional(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('sometimes', $rules['strength']);
        $this->assertStringContainsString('sometimes', $rules['dexterity']);
        $this->assertStringContainsString('sometimes', $rules['vitality']);
        $this->assertStringContainsString('sometimes', $rules['energy']);
    }

    public function test_stat_attributes_must_be_non_negative(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('min:0', $rules['strength']);
        $this->assertStringContainsString('min:0', $rules['dexterity']);
        $this->assertStringContainsString('min:0', $rules['vitality']);
        $this->assertStringContainsString('min:0', $rules['energy']);
    }

    public function test_messages_contains_custom_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('character_id.required', $messages);
        $this->assertArrayHasKey('character_id.exists', $messages);
        $this->assertArrayHasKey('strength.min', $messages);
    }

    public function test_messages_are_in_chinese(): void
    {
        $messages = $this->request->messages();

        $this->assertStringContainsString('角色ID', $messages['character_id.required']);
        $this->assertStringContainsString('力量', $messages['strength.min']);
    }
}
