<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameCharacterSkill;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameSkillDefinition;
use App\Models\User;
use App\Services\Game\GameCharacterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GameCharacterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GameCharacterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GameCharacterService;
        Cache::flush();
    }

    public function test_get_character_list_returns_empty_data_when_user_has_no_characters(): void
    {
        $result = $this->service->getCharacterList(999999);

        $this->assertSame([], $result['characters']->all());
        $this->assertSame(config('game.experience_table', []), $result['experience_table']);
    }

    public function test_get_character_list_reconciles_levels_and_returns_only_user_characters(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $character = $this->createCharacter($user, [
            'name' => 'ReconcileHero',
            'level' => 1,
            'experience' => 150,
        ]);
        $this->createCharacter($otherUser, ['name' => 'OtherHero']);

        $result = $this->service->getCharacterList($user->id);

        $this->assertCount(1, $result['characters']);
        $this->assertSame('ReconcileHero', $result['characters']->first()['name']);
        $this->assertSame(2, $character->fresh()->level);
    }

    public function test_get_character_detail_returns_null_when_no_character_matches(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->service->getCharacterDetail($user->id, 999999));
    }

    public function test_get_character_detail_returns_first_character_when_no_id_is_provided(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'name' => 'DetailHero',
            'experience' => 150,
        ]);

        $definition = $this->createItemDefinition();
        $equippedItem = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $definition->base_stats,
            'affixes' => [],
            'is_in_storage' => false,
            'is_equipped' => true,
            'quantity' => 1,
            'slot_index' => null,
            'sockets' => 0,
            'sell_price' => 0,
        ]);
        GameEquipment::create([
            'character_id' => $character->id,
            'slot' => 'weapon',
            'item_id' => $equippedItem->id,
        ]);

        $result = $this->service->getCharacterDetail($user->id);

        $this->assertSame($character->id, $result['character']->id);
        $this->assertSame(2, $result['character']->fresh()->level);
        $this->assertArrayHasKey('weapon', $result['equipped_items']);
        $this->assertSame($equippedItem->id, $result['equipped_items']['weapon']->id);
        $this->assertArrayHasKey('attack', $result['combat_stats']);
        $this->assertArrayHasKey('attack', $result['stats_breakdown']);
    }

    public function test_create_character_creates_equipment_slots_and_clears_cached_list(): void
    {
        $user = User::factory()->create();
        config([
            'game.starting_copper.warrior' => 77,
            'game.class_base_stats.warrior' => [
                'strength' => 9,
                'dexterity' => 4,
                'vitality' => 8,
                'energy' => 3,
            ],
        ]);

        $initial = $this->service->getCharacterList($user->id);
        $this->assertCount(0, $initial['characters']);

        $character = $this->service->createCharacter($user->id, 'NewHero', 'warrior');

        $this->assertSame('NewHero', $character->name);
        $this->assertSame('male', $character->gender);
        $this->assertSame(77, $character->copper);
        $this->assertSame(9, $character->strength);
        $this->assertSame(4, $character->dexterity);
        $this->assertSame(8, $character->vitality);
        $this->assertSame(3, $character->energy);
        $this->assertCount(count(config('game.slots')), $character->equipment);

        $refreshed = $this->service->getCharacterList($user->id);
        $this->assertCount(1, $refreshed['characters']);
    }

    public function test_create_character_validates_name_rules_and_duplicate_names(): void
    {
        $user = User::factory()->create();
        $this->createCharacter($user, ['name' => 'TakenName']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('角色名至少需要2个字符');
        $this->service->createCharacter($user->id, 'A', 'warrior');
    }

    public function test_create_character_rejects_invalid_characters_and_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->createCharacter($user, ['name' => 'TakenName']);

        try {
            $this->service->createCharacter($user->id, 'Bad Name!', 'warrior');
            $this->fail('Expected invalid character name exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('角色名只能包含中文、英文和数字', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('角色名已被使用');
        $this->service->createCharacter($user->id, 'TakenName', 'warrior');
    }

    public function test_delete_character_removes_character_and_clears_cached_list(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['name' => 'DeleteMe']);

        $this->service->getCharacterList($user->id);
        $this->service->deleteCharacter($user->id, $character->id);

        $this->assertNull(GameCharacter::find($character->id));
        $this->assertCount(0, $this->service->getCharacterList($user->id)['characters']);
    }

    public function test_allocate_stats_updates_attributes_and_ignores_negative_values(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'stat_points' => 5,
        ]);

        $result = $this->service->allocateStats($user->id, $character->id, [
            'strength' => 2,
            'dexterity' => -5,
            'vitality' => 1,
        ]);

        $fresh = $character->fresh();
        $this->assertSame(12, $fresh->strength);
        $this->assertSame(10, $fresh->dexterity);
        $this->assertSame(11, $fresh->vitality);
        $this->assertSame(2, $fresh->stat_points);
        $this->assertSame($fresh->id, $result['character']->id);
        $this->assertArrayHasKey('combat_stats', $result);
        $this->assertArrayHasKey('stats_breakdown', $result);
    }

    public function test_allocate_stats_throws_when_points_are_insufficient(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'stat_points' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('属性点不足');
        $this->service->allocateStats($user->id, $character->id, ['strength' => 2]);
    }

    public function test_update_difficulty_updates_first_character_when_id_is_omitted(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['difficulty_tier' => 0]);

        $result = $this->service->updateDifficulty($user->id, 3);

        $this->assertSame($character->id, $result->id);
        $this->assertSame(3, $result->difficulty_tier);
    }

    public function test_get_character_full_detail_returns_inventory_storage_skills_and_available_skills(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'name' => 'FullHero',
            'class' => 'warrior',
        ]);
        $weaponDefinition = $this->createItemDefinition([
            'name' => 'Inventory Sword',
            'type' => 'weapon',
            'sub_type' => 'sword',
        ]);
        $storageDefinition = $this->createItemDefinition([
            'name' => 'Stored Amulet',
            'type' => 'amulet',
            'sub_type' => null,
            'base_stats' => ['max_hp' => 25],
        ]);

        GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $weaponDefinition->id,
            'quality' => 'common',
            'stats' => $weaponDefinition->base_stats,
            'affixes' => [],
            'is_in_storage' => false,
            'is_equipped' => false,
            'quantity' => 1,
            'slot_index' => 0,
            'sockets' => 0,
            'sell_price' => 10,
        ]);
        GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $storageDefinition->id,
            'quality' => 'common',
            'stats' => $storageDefinition->base_stats,
            'affixes' => [],
            'is_in_storage' => true,
            'is_equipped' => false,
            'quantity' => 1,
            'slot_index' => 0,
            'sockets' => 0,
            'sell_price' => 12,
        ]);

        $warriorSkill = GameSkillDefinition::create([
            'name' => 'Slash',
            'description' => 'Warrior slash',
            'type' => 'active',
            'class_restriction' => 'warrior',
            'mana_cost' => 0,
            'cooldown' => 1,
            'skill_points_cost' => 1,
            'max_level' => 10,
            'base_damage' => 12,
            'damage_per_level' => 2,
            'mana_cost_per_level' => 0,
            'icon' => 'slash',
            'effects' => [],
            'target_type' => 'single',
            'is_active' => true,
        ]);
        GameSkillDefinition::create([
            'name' => 'Arcane Burst',
            'description' => 'Mage only',
            'type' => 'active',
            'class_restriction' => 'mage',
            'mana_cost' => 5,
            'cooldown' => 1,
            'skill_points_cost' => 1,
            'max_level' => 10,
            'base_damage' => 20,
            'damage_per_level' => 4,
            'mana_cost_per_level' => 1,
            'icon' => 'burst',
            'effects' => [],
            'target_type' => 'single',
            'is_active' => true,
        ]);
        GameSkillDefinition::create([
            'name' => 'Disabled Skill',
            'description' => 'Inactive',
            'type' => 'active',
            'class_restriction' => 'all',
            'mana_cost' => 0,
            'cooldown' => 1,
            'skill_points_cost' => 1,
            'max_level' => 10,
            'base_damage' => 5,
            'damage_per_level' => 1,
            'mana_cost_per_level' => 0,
            'icon' => 'disabled',
            'effects' => [],
            'target_type' => 'single',
            'is_active' => false,
        ]);

        $characterSkill = new GameCharacterSkill([
            'character_id' => $character->id,
            'skill_id' => $warriorSkill->id,
        ]);
        $characterSkill->level = 2;
        $characterSkill->slot_index = 1;
        $characterSkill->save();

        $result = $this->service->getCharacterFullDetail($user->id, $character->id);

        $this->assertCount(1, $result['inventory']);
        $this->assertCount(1, $result['storage']);
        $this->assertCount(1, $result['skills']);
        $availableSkillNames = $result['available_skills']->pluck('name')->all();
        $this->assertContains('Slash', $availableSkillNames);
        $this->assertNotContains('Arcane Burst', $availableSkillNames);
        $this->assertNotContains('Disabled Skill', $availableSkillNames);
    }

    public function test_check_offline_rewards_returns_unavailable_when_recently_online(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'last_online' => now()->subSeconds(30),
        ]);

        $result = $this->service->checkOfflineRewards($character);

        $this->assertFalse($result['available']);
        $this->assertSame(30, $result['offline_seconds']);
        $this->assertSame(0, $result['experience']);
        $this->assertSame(0, $result['copper']);
    }

    public function test_check_offline_rewards_caps_time_and_flags_level_up(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'level' => 1,
            'experience' => 90,
            'last_online' => now()->subDays(3),
        ]);

        $result = $this->service->checkOfflineRewards($character);

        $this->assertTrue($result['available']);
        $this->assertSame(config('game.offline_rewards.max_seconds'), $result['offline_seconds']);
        $this->assertTrue($result['level_up']);
        $this->assertGreaterThan(0, $result['experience']);
        $this->assertGreaterThan(0, $result['copper']);
    }

    public function test_claim_offline_rewards_returns_zeroes_when_rewards_are_unavailable(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'last_online' => now()->subSeconds(10),
        ]);

        $result = $this->service->claimOfflineRewards($character);

        $this->assertSame([
            'experience' => 0,
            'copper' => 0,
            'level_up' => false,
            'new_level' => $character->level,
        ], $result);
    }

    public function test_claim_offline_rewards_applies_rewards_and_prevents_immediate_reclaim(): void
    {
        Carbon::setTestNow('2026-02-28 12:00:00');

        try {
            $user = User::factory()->create();
            $character = $this->createCharacter($user, [
                'level' => 1,
                'experience' => 90,
                'copper' => 10,
                'last_online' => now()->subMinutes(2),
            ]);

            $result = $this->service->claimOfflineRewards($character);

            $fresh = $character->fresh();
            $this->assertTrue($result['level_up']);
            $this->assertSame(2, $result['new_level']);
            $this->assertSame(210, $fresh->experience);
            $this->assertSame(70, $fresh->copper);
            $this->assertNotNull($fresh->claimed_offline_at);

            $recheck = $this->service->checkOfflineRewards($fresh);
            $this->assertFalse($recheck['available']);
            $this->assertLessThan(60, $recheck['offline_seconds']);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Hero' . $user->id,
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 1,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
            'is_fighting' => false,
            'difficulty_tier' => 0,
            'discovered_items' => [],
            'discovered_monsters' => [],
        ], $attributes));
    }

    private function createItemDefinition(array $attributes = []): GameItemDefinition
    {
        return GameItemDefinition::create(array_merge([
            'name' => 'Basic Sword',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'sockets' => 0,
            'gem_stats' => null,
            'base_stats' => ['attack' => 10],
            'required_level' => 1,
            'icon' => 'sword',
            'description' => 'Basic item definition',
            'is_active' => true,
            'buy_price' => 100,
        ], $attributes));
    }
}
