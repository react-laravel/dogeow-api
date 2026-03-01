<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\AllocateStatsRequest;
use App\Http\Requests\Game\BuyItemRequest;
use App\Http\Requests\Game\CreateCharacterRequest;
use App\Http\Requests\Game\EquipItemRequest;
use App\Http\Requests\Game\LearnSkillRequest;
use App\Http\Requests\Game\MoveItemRequest;
use App\Http\Requests\Game\SellItemRequest;
use App\Http\Requests\Game\SocketGemRequest;
use App\Http\Requests\Game\UnsocketGemRequest;
use App\Http\Requests\Game\UpdateDifficultyRequest;
use App\Http\Requests\Game\UpdatePotionSettingsRequest;
use App\Http\Requests\Game\UsePotionRequest;
use Tests\TestCase;

class GameRequestTest extends TestCase
{
    public function test_allocate_stats_request_authorize(): void
    {
        $request = new AllocateStatsRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_allocate_stats_request_rules(): void
    {
        $request = new AllocateStatsRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('character_id', $rules);
        $this->assertArrayHasKey('strength', $rules);
    }

    public function test_buy_item_request_authorize(): void
    {
        $request = new BuyItemRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_buy_item_request_rules(): void
    {
        $request = new BuyItemRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('item_id', $rules);
    }

    public function test_create_character_request_authorize(): void
    {
        $request = new CreateCharacterRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_create_character_request_rules(): void
    {
        $request = new CreateCharacterRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
    }

    public function test_equip_item_request_authorize(): void
    {
        $request = new EquipItemRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_equip_item_request_rules(): void
    {
        $request = new EquipItemRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('item_id', $rules);
    }

    public function test_learn_skill_request_authorize(): void
    {
        $request = new LearnSkillRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_learn_skill_request_rules(): void
    {
        $request = new LearnSkillRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('skill_id', $rules);
    }

    public function test_move_item_request_authorize(): void
    {
        $request = new MoveItemRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_move_item_request_rules(): void
    {
        $request = new MoveItemRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('item_id', $rules);
    }

    public function test_sell_item_request_authorize(): void
    {
        $request = new SellItemRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_sell_item_request_rules(): void
    {
        $request = new SellItemRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('item_id', $rules);
    }

    public function test_socket_gem_request_authorize(): void
    {
        $request = new SocketGemRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_socket_gem_request_rules(): void
    {
        $request = new SocketGemRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('item_id', $rules);
    }

    public function test_unsocket_gem_request_authorize(): void
    {
        $request = new UnsocketGemRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_unsocket_gem_request_rules(): void
    {
        $request = new UnsocketGemRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('item_id', $rules);
    }

    public function test_unequip_item_request_authorize(): void
    {
        $request = new \App\Http\Requests\Game\UnequipItemRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_unequip_item_request_rules(): void
    {
        $request = new \App\Http\Requests\Game\UnequipItemRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('slot', $rules);
    }

    public function test_update_difficulty_request_authorize(): void
    {
        $request = new UpdateDifficultyRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_update_difficulty_request_rules(): void
    {
        $request = new UpdateDifficultyRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('difficulty_tier', $rules);
    }

    public function test_update_potion_settings_request_authorize(): void
    {
        $request = new UpdatePotionSettingsRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_update_potion_settings_request_rules(): void
    {
        $request = new UpdatePotionSettingsRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('auto_use_hp_potion', $rules);
    }

    public function test_use_potion_request_authorize(): void
    {
        $request = new UsePotionRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_use_potion_request_rules(): void
    {
        $request = new UsePotionRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('item_id', $rules);
    }
}
