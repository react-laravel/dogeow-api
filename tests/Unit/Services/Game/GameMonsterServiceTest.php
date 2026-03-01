<?php

namespace App\Services\Game;

final class GameMonsterRandomControl
{
    /** @var array<int, int> */
    public static array $randQueue = [];
}

function rand(int $min, int $max): int
{
    if (GameMonsterRandomControl::$randQueue !== []) {
        return array_shift(GameMonsterRandomControl::$randQueue);
    }

    return \rand($min, $max);
}

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\User;
use App\Services\Game\GameMonsterRandomControl;
use App\Services\Game\GameMonsterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GameMonsterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GameMonsterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GameMonsterService;
        GameMonsterRandomControl::$randQueue = [];
        config(['game.combat.monster_refresh_interval' => 60]);
    }

    public function test_should_refresh_monsters_checks_missing_recent_and_expired_timestamps(): void
    {
        $missing = $this->createCharacter(['combat_monsters_refreshed_at' => null]);
        $recent = $this->createCharacter([
            'combat_monsters_refreshed_at' => Carbon::now()->subSeconds(10),
        ]);
        $expired = $this->createCharacter([
            'combat_monsters_refreshed_at' => Carbon::now()->subSeconds(70),
        ]);

        $this->assertTrue($this->service->shouldRefreshMonsters($missing));
        $this->assertFalse($this->service->shouldRefreshMonsters($recent));
        $this->assertTrue($this->service->shouldRefreshMonsters($expired));
    }

    public function test_prepare_monster_info_returns_existing_alive_monster_when_not_refreshing(): void
    {
        $monster = $this->createMonster([
            'name' => 'Cached Skeleton',
            'hp_base' => 22,
            'attack_base' => 7,
            'defense_base' => 3,
            'experience_base' => 11,
        ]);
        $map = $this->createMap([$monster->id]);
        $character = $this->createCharacter([
            'combat_monsters_refreshed_at' => now(),
            'combat_monsters' => [
                [
                    'id' => $monster->id,
                    'name' => $monster->name,
                    'type' => $monster->type,
                    'level' => 4,
                    'hp' => 12,
                    'max_hp' => 20,
                    'position' => 0,
                ],
                null,
                null,
                null,
                null,
            ],
        ]);

        [$loadedMonster, $level, $stats, $totalHp, $totalMaxHp] = $this->service->prepareMonsterInfo($character, $map);

        $this->assertSame($monster->id, $loadedMonster?->id);
        $this->assertSame(4, $level);
        $this->assertSame($monster->getCombatStats(), $stats);
        $this->assertSame(12, $totalHp);
        $this->assertSame(20, $totalMaxHp);
    }

    public function test_load_existing_monsters_clears_invalid_state_when_no_definitions_match(): void
    {
        $character = $this->createCharacter([
            'combat_monsters' => [[
                'id' => 999999,
                'level' => 2,
                'hp' => 10,
                'max_hp' => 15,
                'position' => 0,
            ]],
            'combat_monster_id' => 999999,
            'combat_monster_hp' => 10,
            'combat_monster_max_hp' => 15,
        ]);

        $result = $this->service->loadExistingMonsters($character, $character->combat_monsters);

        $this->assertSame([null, null, null, 0, 0], $result);
        $this->assertNull($character->combat_monsters);
        $this->assertNull($character->combat_monster_id);
        $this->assertNull($character->combat_monster_hp);
    }

    public function test_generate_new_monsters_returns_empty_tuple_when_map_has_no_monsters(): void
    {
        $character = $this->createCharacter();
        $map = $this->createMap([]);

        $result = $this->service->generateNewMonsters($character, $map, []);

        $this->assertSame([null, null, null, 0, 0], $result);
    }

    public function test_generate_new_monsters_populates_fixed_slots_and_resets_combat_state(): void
    {
        $monster = $this->createMonster([
            'name' => 'Spawner',
            'level' => 6,
            'hp_base' => 20,
            'attack_base' => 8,
            'defense_base' => 3,
            'experience_base' => 15,
        ]);
        $map = $this->createMap([$monster->id]);
        $character = $this->createCharacter([
            'difficulty_tier' => 1,
            'combat_total_damage_dealt' => 99,
            'combat_total_damage_taken' => 44,
            'combat_rounds' => 7,
            'combat_skills_used' => [1001],
            'combat_skill_cooldowns' => ['1001' => 2],
        ]);
        GameMonsterRandomControl::$randQueue = [2, 0, 0, 0];

        [$firstMonster, $firstLevel, $monsterStats, $totalHp, $totalMaxHp] = $this->service->generateNewMonsters($character, $map, []);

        $fresh = $character->fresh();

        $this->assertSame($monster->id, $firstMonster?->id);
        $this->assertSame($monster->level, $firstLevel);
        $this->assertSame($monster->getCombatStats(), $monsterStats);
        $this->assertCount(5, $fresh->combat_monsters);
        $this->assertIsArray($fresh->combat_monsters[0]);
        $this->assertIsArray($fresh->combat_monsters[1]);
        $this->assertNull($fresh->combat_monsters[2]);
        $this->assertSame($fresh->combat_monster_hp, $totalHp);
        $this->assertSame($fresh->combat_monster_max_hp, $totalMaxHp);
        $this->assertSame(0, $fresh->combat_total_damage_dealt);
        $this->assertSame(0, $fresh->combat_total_damage_taken);
        $this->assertSame(0, $fresh->combat_rounds);
        $this->assertNull($fresh->combat_skills_used);
        $this->assertNull($fresh->combat_skill_cooldowns);
        $this->assertNotNull($fresh->combat_started_at);
        $this->assertNotNull($fresh->combat_monsters_refreshed_at);
    }

    public function test_generate_new_monsters_refresh_preserves_hp_and_instance_id_by_position(): void
    {
        $monster = $this->createMonster([
            'name' => 'Refreshed Bat',
            'level' => 5,
            'hp_base' => 30,
        ]);
        $map = $this->createMap([$monster->id]);
        $character = $this->createCharacter();
        GameMonsterRandomControl::$randQueue = [1, 0, 0];

        $this->service->generateNewMonsters($character, $map, [[
            'id' => $monster->id,
            'instance_id' => 'persisted-instance',
            'name' => $monster->name,
            'type' => $monster->type,
            'level' => 5,
            'hp' => 7,
            'max_hp' => 30,
            'position' => 0,
        ]], true);

        $refreshedMonster = $character->fresh()->combat_monsters[0];

        $this->assertSame('persisted-instance', $refreshedMonster['instance_id']);
        $this->assertSame(7, $refreshedMonster['hp']);
        $this->assertGreaterThanOrEqual(7, $refreshedMonster['max_hp']);
    }

    public function test_try_add_new_monsters_generates_into_all_available_slots_and_syncs_totals(): void
    {
        $monster = $this->createMonster([
            'name' => 'Adder',
            'level' => 4,
            'hp_base' => 18,
            'attack_base' => 6,
            'defense_base' => 2,
            'experience_base' => 9,
        ]);
        $map = $this->createMap([$monster->id]);
        $character = $this->createCharacter([
            'combat_monsters' => [
                [
                    'id' => $monster->id,
                    'name' => $monster->name,
                    'type' => $monster->type,
                    'level' => 4,
                    'hp' => 10,
                    'max_hp' => 18,
                    'position' => 0,
                ],
                [
                    'id' => $monster->id,
                    'name' => $monster->name,
                    'type' => $monster->type,
                    'level' => 4,
                    'hp' => 8,
                    'max_hp' => 18,
                    'position' => 1,
                ],
                null,
                null,
                null,
            ],
        ]);
        $roundResult = [
            'slots_where_monster_died_this_round' => [],
            'new_monster_hp' => 18,
            'new_monster_max_hp' => 36,
        ];
        GameMonsterRandomControl::$randQueue = [50, 80, 0, 0, 0, 0];

        $result = $this->service->tryAddNewMonsters($character, $map, $roundResult, 2);

        $this->assertCount(5, $character->combat_monsters);
        $this->assertIsArray($character->combat_monsters[2]);
        $this->assertIsArray($character->combat_monsters[3]);
        $this->assertIsArray($character->combat_monsters[4]);
        $this->assertSame(
            array_sum(array_column(array_filter($character->combat_monsters, 'is_array'), 'hp')),
            $result['new_monster_hp']
        );
        $this->assertSame(
            array_sum(array_column(array_filter($character->combat_monsters, 'is_array'), 'max_hp')),
            $result['new_monster_max_hp']
        );
    }

    public function test_try_add_new_monsters_respects_just_died_slots_and_empty_maps(): void
    {
        $monster = $this->createMonster([
            'name' => 'Guard',
            'hp_base' => 16,
        ]);
        $character = $this->createCharacter([
            'combat_monsters' => [
                ['id' => $monster->id, 'hp' => 0, 'max_hp' => 16, 'position' => 0],
                ['id' => $monster->id, 'hp' => 5, 'max_hp' => 16, 'position' => 1],
                null,
                null,
                null,
            ],
        ]);

        $blockedResult = $this->service->tryAddNewMonsters($character, $this->createMap([]), [
            'slots_where_monster_died_this_round' => [0, 2, 3, 4],
            'new_monster_hp' => 0,
            'new_monster_max_hp' => 0,
        ], 1);

        $this->assertNull($character->combat_monsters[2]);
        $this->assertSame(5, $blockedResult['new_monster_hp']);
        $this->assertSame(32, $blockedResult['new_monster_max_hp']);

        $character->combat_monsters = [
            ['id' => $monster->id, 'hp' => 5, 'max_hp' => 16, 'position' => 0],
            null,
            null,
            null,
            null,
        ];
        GameMonsterRandomControl::$randQueue = [50, 40, 0, 0];
        $emptyMapResult = $this->service->tryAddNewMonsters($character, $this->createMap([]), [
            'slots_where_monster_died_this_round' => [],
            'new_monster_hp' => 0,
            'new_monster_max_hp' => 0,
        ], 2);

        $this->assertSame(5, $emptyMapResult['new_monster_hp']);
        $this->assertSame(16, $emptyMapResult['new_monster_max_hp']);
        $this->assertNull($character->combat_monsters[1]);
    }

    public function test_format_monsters_for_response_returns_fixed_slots_and_default_fallback(): void
    {
        $character = $this->createCharacter([
            'combat_monsters' => [
                ['id' => 1, 'name' => 'Dead', 'hp' => 0, 'max_hp' => 10],
                null,
                ['id' => 2, 'name' => 'Alive', 'hp' => 7, 'max_hp' => 9],
            ],
        ]);

        $result = $this->service->formatMonstersForResponse($character);

        $this->assertCount(5, $result['monsters']);
        $this->assertSame(0, $result['monsters'][0]['position']);
        $this->assertSame(2, $result['monsters'][2]['position']);
        $this->assertSame('Alive', $result['first_alive_monster']['name']);

        $character->combat_monsters = [null, null, null, null, null];
        $fallback = $this->service->formatMonstersForResponse($character);
        $this->assertSame('怪物', $fallback['first_alive_monster']['name']);
    }

    private function createCharacter(array $attributes = []): GameCharacter
    {
        $user = User::factory()->create();

        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'MonsterHero' . $user->id,
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
            'is_fighting' => true,
            'current_hp' => 30,
            'current_mana' => 10,
            'discovered_items' => [],
            'discovered_monsters' => [],
        ], $attributes));
    }

    /**
     * @param  array<int,int>  $monsterIds
     */
    private function createMap(array $monsterIds): GameMapDefinition
    {
        return GameMapDefinition::create([
            'name' => 'Monster Map ' . uniqid(),
            'act' => 1,
            'monster_ids' => $monsterIds,
            'background' => 'bg',
            'description' => 'monster test map',
            'is_active' => true,
        ]);
    }

    private function createMonster(array $attributes = []): GameMonsterDefinition
    {
        return GameMonsterDefinition::create(array_merge([
            'name' => 'Test Monster',
            'type' => 'normal',
            'level' => 3,
            'hp_base' => 20,
            'attack_base' => 6,
            'defense_base' => 2,
            'experience_base' => 10,
            'drop_table' => [],
            'icon' => 'monster',
            'is_active' => true,
        ], $attributes));
    }
}
