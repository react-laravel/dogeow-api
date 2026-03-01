<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\UsePotionRequest;
use Tests\TestCase;

class UsePotionRequestTest extends TestCase
{
    private UsePotionRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new UsePotionRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_has_item_id_required(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('item_id', $rules);
        $this->assertStringContainsString('required', $rules['item_id']);
    }

    public function test_item_id_must_be_integer(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('integer', $rules['item_id']);
    }

    public function test_item_id_must_exist_in_game_items(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('exists', $rules['item_id']);
        $this->assertStringContainsString('game_items', $rules['item_id']);
    }

    public function test_messages_in_chinese(): void
    {
        $messages = $this->request->messages();

        $this->assertStringContainsString('物品ID', $messages['item_id.required']);
    }
}
