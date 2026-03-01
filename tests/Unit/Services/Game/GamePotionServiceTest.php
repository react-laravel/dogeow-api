<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use App\Services\Game\GamePotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePotionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GamePotionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GamePotionService;
    }

    public function test_try_auto_use_potions_uses_best_hp_and_mp_potions_when_thresholds_are_met(): void
    {
        $character = $this->createCharacter([
            'auto_use_hp_potion' => true,
            'hp_potion_threshold' => 50,
            'auto_use_mp_potion' => true,
            'mp_potion_threshold' => 50,
            'current_hp' => 40,
            'current_mana' => 10,
        ]);
        $minorHp = $this->createPotion($character, '小血瓶', 'hp', ['max_hp' => 20], ['quantity' => 2]);
        $majorHp = $this->createPotion($character, '大血瓶', 'hp', ['max_hp' => 60], ['quantity' => 1, 'slot_index' => 1]);
        $majorMp = $this->createPotion($character, '大蓝瓶', 'mp', ['max_mana' => 30], ['quantity' => 2, 'slot_index' => 2]);

        $used = $this->service->tryAutoUsePotions($character, 40, 10, [
            'max_hp' => 100,
            'max_mana' => 80,
        ]);

        $this->assertSame('大血瓶', $used['hp']['name']);
        $this->assertSame(60, $used['hp']['restored']);
        $this->assertSame('大蓝瓶', $used['mp']['name']);
        $this->assertSame(30, $used['mp']['restored']);
        $this->assertNull(GameItem::find($majorHp->id));
        $this->assertSame(1, $majorMp->fresh()->quantity);
        $this->assertSame(2, $minorHp->fresh()->quantity);
        $this->assertSame(70, $character->fresh()->current_hp);
        $this->assertSame(40, $character->fresh()->current_mana);
    }

    public function test_try_auto_use_potions_returns_empty_when_disabled_or_above_threshold(): void
    {
        $character = $this->createCharacter([
            'auto_use_hp_potion' => false,
            'hp_potion_threshold' => 0,
            'auto_use_mp_potion' => true,
            'mp_potion_threshold' => 0,
            'current_hp' => 95,
            'current_mana' => 79,
        ]);
        $hpPotion = $this->createPotion($character, '备用血瓶', 'hp', ['max_hp' => 50], ['quantity' => 1]);
        $mpPotion = $this->createPotion($character, '备用蓝瓶', 'mp', ['max_mana' => 50], ['quantity' => 1, 'slot_index' => 1]);

        $used = $this->service->tryAutoUsePotions($character, 95, 79, [
            'max_hp' => 100,
            'max_mana' => 80,
        ]);

        $this->assertSame([], $used);
        $this->assertNotNull($hpPotion->fresh());
        $this->assertNotNull($mpPotion->fresh());
    }

    public function test_find_best_potion_prefers_highest_restore_and_ignores_storage_items(): void
    {
        $character = $this->createCharacter();
        $small = $this->createPotion($character, '小血瓶', 'hp', ['max_hp' => 20], ['slot_index' => 0]);
        $large = $this->createPotion($character, '大血瓶', 'hp', ['max_hp' => 80], ['slot_index' => 1]);
        $this->createPotion($character, '仓库血瓶', 'hp', ['max_hp' => 200], [
            'is_in_storage' => true,
            'slot_index' => 0,
        ]);

        $best = $this->service->findBestPotion($character, 'hp');

        $this->assertSame($large->id, $best?->id);
        $this->assertNotSame($small->id, $best?->id);
    }

    public function test_use_potion_item_restores_values_and_handles_stack_or_delete(): void
    {
        $character = $this->createCharacter([
            'current_hp' => 20,
            'current_mana' => 5,
        ]);
        $stackPotion = $this->createPotion($character, '叠加药剂', 'hp', ['max_hp' => 30, 'max_mana' => 20], [
            'quantity' => 2,
            'slot_index' => 0,
        ]);
        $singlePotion = $this->createPotion($character, '单次药剂', 'mp', ['max_mana' => 15], [
            'quantity' => 1,
            'slot_index' => 1,
        ]);

        $this->service->usePotionItem($character, $stackPotion);
        $this->assertSame(1, $stackPotion->fresh()->quantity);
        $this->assertSame(50, $character->fresh()->current_hp);
        $this->assertSame(25, $character->fresh()->current_mana);

        $this->service->usePotionItem($character->fresh(), $singlePotion->fresh());
        $this->assertNull(GameItem::find($singlePotion->id));
    }

    public function test_has_potion_and_get_potion_count_work_with_optional_type_filter(): void
    {
        $character = $this->createCharacter();
        $this->createPotion($character, '血瓶', 'hp', ['max_hp' => 20], ['slot_index' => 0]);
        $this->createPotion($character, '蓝瓶', 'mp', ['max_mana' => 20], ['slot_index' => 1]);
        $this->createPotion($character, '仓库血瓶', 'hp', ['max_hp' => 20], [
            'is_in_storage' => true,
            'slot_index' => 0,
        ]);

        $this->assertTrue($this->service->hasPotion($character, 'hp'));
        $this->assertTrue($this->service->hasPotion($character, 'mp'));
        $this->assertSame(2, $this->service->getPotionCount($character));
        $this->assertSame(1, $this->service->getPotionCount($character, 'hp'));
        $this->assertSame(1, $this->service->getPotionCount($character, 'mp'));
    }

    public function test_get_all_potions_returns_formatted_inventory_rows(): void
    {
        $character = $this->createCharacter();
        $hpPotion = $this->createPotion($character, '生命药水', 'hp', ['max_hp' => 35], [
            'quantity' => 2,
            'slot_index' => 0,
        ]);
        $mpPotion = $this->createPotion($character, '法力药水', 'mp', ['max_mana' => 18], [
            'quantity' => 1,
            'slot_index' => 1,
        ]);

        $result = $this->service->getAllPotions($character);

        $this->assertCount(2, $result);
        $this->assertSame($hpPotion->id, $result[0]['id']);
        $this->assertSame('hp', $result[0]['type']);
        $this->assertSame('生命药水', $result[0]['name']);
        $this->assertSame(2, $result[0]['quantity']);
        $this->assertSame(35, $result[0]['restore_hp']);
        $this->assertSame($mpPotion->id, $result[1]['id']);
        $this->assertSame(18, $result[1]['restore_mp']);
    }

    private function createCharacter(array $attributes = []): GameCharacter
    {
        $user = User::factory()->create();

        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'PotionHero' . $user->id,
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 10,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
            'difficulty_tier' => 0,
            'is_fighting' => false,
            'current_hp' => 30,
            'current_mana' => 10,
            'discovered_items' => [],
            'discovered_monsters' => [],
        ], $attributes));
    }

    /**
     * @param  array<string,mixed>  $baseStats
     * @param  array<string,mixed>  $itemAttributes
     */
    private function createPotion(
        GameCharacter $character,
        string $name,
        string $subType,
        array $baseStats,
        array $itemAttributes = []
    ): GameItem {
        $definition = GameItemDefinition::create([
            'name' => $name,
            'type' => 'potion',
            'sub_type' => $subType,
            'sockets' => 0,
            'gem_stats' => null,
            'base_stats' => $baseStats,
            'required_level' => 1,
            'icon' => 'potion',
            'description' => 'Potion test definition',
            'is_active' => true,
            'buy_price' => 20,
        ]);

        return GameItem::create(array_merge([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $baseStats,
            'affixes' => [],
            'is_in_storage' => false,
            'is_equipped' => false,
            'quantity' => 1,
            'slot_index' => 0,
            'sockets' => 0,
            'sell_price' => 1,
        ], $itemAttributes))->load('definition');
    }
}
