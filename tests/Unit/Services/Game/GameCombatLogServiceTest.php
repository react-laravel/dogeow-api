<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameCombatLog;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\User;
use App\Services\Game\GameCombatLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCombatLogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GameCombatLogService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GameCombatLogService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_create_round_log_persists_full_round_detail_payload(): void
    {
        $character = $this->createCharacter([
            'level' => 11,
            'class' => 'ranger',
        ]);
        $map = $this->createMap();
        $monster = $this->createMonster();

        $log = $this->service->createRoundLog($character, $map, $monster->id, [
            'round_damage_dealt' => 45,
            'round_damage_taken' => 12,
            'victory' => true,
            'loot' => ['item' => 'fang'],
            'experience_gained' => 33,
            'copper_gained' => 14,
            'skills_used_this_round' => [101, 202],
            'round_details' => [
                'character' => [
                    'level' => 12,
                    'class' => 'ranger',
                    'attack' => 99,
                    'defense' => 44,
                    'crit_rate' => 0.3,
                    'crit_damage' => 1.9,
                ],
                'monster' => [
                    'level' => 8,
                    'hp' => 10,
                    'max_hp' => 60,
                    'attack' => 18,
                    'defense' => 7,
                    'experience' => 25,
                ],
                'damage' => [
                    'base_attack' => 30,
                    'skill_damage' => 10,
                    'crit_damage' => 5,
                    'aoe_damage' => 2,
                    'total' => 47,
                    'defense_reduction' => 0.2,
                    'monster_counter' => 12,
                ],
                'battle' => [
                    'round' => 4,
                    'alive_count' => 2,
                    'killed_count' => 1,
                ],
                'difficulty' => [
                    'tier' => 3,
                    'multiplier' => 3.0,
                ],
            ],
        ], ['hp' => ['name' => '小血瓶']], ['mp' => ['name' => '小蓝瓶']]);

        $fresh = GameCombatLog::findOrFail($log->id);

        $this->assertSame($character->id, $fresh->character_id);
        $this->assertSame($map->id, $fresh->map_id);
        $this->assertSame($monster->id, $fresh->monster_id);
        $this->assertTrue($fresh->victory);
        $this->assertSame(['item' => 'fang'], $fresh->loot_dropped);
        $this->assertSame([101, 202], $fresh->skills_used);
        $this->assertSame(['before' => ['hp' => ['name' => '小血瓶']], 'after' => ['mp' => ['name' => '小蓝瓶']]], $fresh->potion_used);
        $this->assertSame(12, $fresh->character_level);
        $this->assertSame(99, $fresh->character_attack);
        $this->assertSame(8, $fresh->monster_level);
        $this->assertSame(10, $fresh->monster_hp);
        $this->assertSame(12, $fresh->monster_counter_damage);
        $this->assertSame(4, $fresh->round_number);
        $this->assertSame(3, $fresh->difficulty_tier);
    }

    public function test_create_round_log_falls_back_to_character_defaults_and_nulls_empty_potion_sections(): void
    {
        $character = $this->createCharacter([
            'level' => 7,
            'class' => 'warrior',
        ]);
        $map = $this->createMap();

        $log = $this->service->createRoundLog($character, $map, 12345, [
            'round_damage_dealt' => 5,
            'round_damage_taken' => 3,
            'skills_used_this_round' => [],
        ], [], []);

        $this->assertSame(7, $log->character_level);
        $this->assertSame('warrior', $log->character_class);
        $this->assertNull($log->loot_dropped);
        $this->assertSame(['before' => null, 'after' => null], $log->potion_used);
        $this->assertFalse($log->victory);
    }

    public function test_create_defeat_log_uses_monster_model_fallback_fields_and_duration(): void
    {
        Carbon::setTestNow('2026-02-28 12:05:00');

        $character = $this->createCharacter([
            'class' => 'mage',
            'difficulty_tier' => 2,
            'combat_total_damage_dealt' => 88,
            'combat_total_damage_taken' => 42,
            'combat_started_at' => Carbon::parse('2026-02-28 12:03:00'),
        ]);
        $map = $this->createMap();
        $monster = $this->createMonster([
            'level' => 9,
            'attack_base' => 23,
            'defense_base' => 11,
            'experience_base' => 70,
        ]);

        $log = $this->service->createDefeatLog($character, $map, $monster, [
            'new_skills_aggregated' => [501],
            'monster' => [
                'level' => 10,
                'hp' => 17,
                'max_hp' => 90,
            ],
        ], 6);

        $this->assertFalse($log->victory);
        $this->assertEquals(120, $log->duration_seconds);
        $this->assertSame([501], $log->skills_used);
        $this->assertSame(10, $log->monster_level);
        $this->assertSame(17, $log->monster_hp);
        $this->assertSame(90, $log->monster_max_hp);
        $this->assertSame(23, $log->monster_attack);
        $this->assertSame(11, $log->monster_defense);
        $this->assertSame(70, $log->monster_experience);
        $this->assertSame(2, $log->difficulty_tier);
        $this->assertSame(2.0, (float) $log->difficulty_multiplier);
    }

    public function test_get_combat_logs_returns_latest_fifty_logs_with_relations(): void
    {
        $character = $this->createCharacter();
        $otherCharacter = $this->createCharacter(['name' => 'OtherCombatLogHero']);
        $map = $this->createMap();
        $monster = $this->createMonster();

        foreach (range(1, 55) as $i) {
            GameCombatLog::create([
                'character_id' => $character->id,
                'map_id' => $map->id,
                'monster_id' => $monster->id,
                'damage_dealt' => $i,
                'damage_taken' => 1,
                'victory' => $i % 2 === 0,
                'loot_dropped' => null,
                'experience_gained' => 1,
                'copper_gained' => 1,
                'duration_seconds' => 1,
                'skills_used' => [],
            ]);
        }

        GameCombatLog::create([
            'character_id' => $otherCharacter->id,
            'map_id' => $map->id,
            'monster_id' => $monster->id,
            'damage_dealt' => 999,
            'damage_taken' => 0,
            'victory' => true,
            'loot_dropped' => null,
            'experience_gained' => 0,
            'copper_gained' => 0,
            'duration_seconds' => 0,
            'skills_used' => [],
        ]);

        $result = $this->service->getCombatLogs($character);

        $this->assertCount(50, $result['logs']);
        $this->assertSame(55, $result['logs']->first()->damage_dealt);
        $this->assertSame(6, $result['logs']->last()->damage_dealt);
        $this->assertSame($monster->name, $result['logs']->first()->monster->name);
        $this->assertSame($map->name, $result['logs']->first()->map->name);
    }

    public function test_get_combat_log_detail_returns_error_when_missing_and_formats_existing_log(): void
    {
        Carbon::setTestNow('2026-02-28 13:00:00');

        $character = $this->createCharacter();
        $map = $this->createMap();
        $monster = $this->createMonster();
        $log = GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monster->id,
            'damage_dealt' => 45,
            'damage_taken' => 20,
            'victory' => true,
            'loot_dropped' => ['item' => 'claw'],
            'experience_gained' => 33,
            'copper_gained' => 7,
            'duration_seconds' => 18,
            'skills_used' => [101],
            'potion_used' => ['before' => null, 'after' => ['hp' => ['name' => '药水']]],
            'character_level' => 10,
            'character_class' => 'warrior',
            'character_attack' => 50,
            'character_defense' => 30,
            'character_crit_rate' => 0.1,
            'character_crit_damage' => 1.5,
            'monster_level' => 8,
            'monster_hp' => 0,
            'monster_max_hp' => 40,
            'monster_attack' => 12,
            'monster_defense' => 5,
            'monster_experience' => 20,
            'monster_copper' => 11,
            'base_attack_damage' => 30,
            'skill_damage' => 10,
            'crit_damage' => 5,
            'aoe_damage' => 0,
            'total_damage_to_monsters' => 45,
            'monster_defense_reduction' => 0.2,
            'monster_counter_damage' => 20,
            'round_number' => 3,
            'monsters_alive_count' => 0,
            'monsters_killed_count' => 1,
            'difficulty_tier' => 1,
            'difficulty_multiplier' => 1.75,
        ]);

        $missing = $this->service->getCombatLogDetail($character, 999999);
        $detail = $this->service->getCombatLogDetail($character, $log->id);

        $this->assertSame(['error' => '日志不存在'], $missing);
        $this->assertSame($log->id, $detail['log']['id']);
        $this->assertSame($map->name, $detail['log']['map']['name']);
        $this->assertSame($monster->name, $detail['log']['monster']['name']);
        $this->assertSame(11, $detail['log']['monster_stats']['copper']);
        $this->assertSame(45, $detail['log']['damage_detail']['total']);
        $this->assertSame(1.75, (float) $detail['log']['difficulty']['multiplier']);
        $this->assertSame($log->created_at->toISOString(), $detail['log']['created_at']);
    }

    public function test_get_combat_stats_aggregates_counts_sums_and_looted_items(): void
    {
        $character = $this->createCharacter();
        $map = $this->createMap();
        $monster = $this->createMonster();

        GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monster->id,
            'damage_dealt' => 10,
            'damage_taken' => 2,
            'victory' => true,
            'loot_dropped' => ['item' => 'fang'],
            'experience_gained' => 4,
            'copper_gained' => 2,
            'duration_seconds' => 3,
            'skills_used' => [],
        ]);
        GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monster->id,
            'damage_dealt' => 8,
            'damage_taken' => 5,
            'victory' => false,
            'loot_dropped' => null,
            'experience_gained' => 1,
            'copper_gained' => 3,
            'duration_seconds' => 2,
            'skills_used' => [],
        ]);

        $result = $this->service->getCombatStats($character);

        $this->assertSame(2, $result['stats']['total_battles']);
        $this->assertSame(1, $result['stats']['total_victories']);
        $this->assertSame(1, $result['stats']['total_defeats']);
        $this->assertSame(18, $result['stats']['total_damage_dealt']);
        $this->assertSame(7, $result['stats']['total_damage_taken']);
        $this->assertSame(5, $result['stats']['total_experience_gained']);
        $this->assertSame(5, $result['stats']['total_copper_gained']);
        $this->assertSame(1, $result['stats']['total_items_looted']);
    }

    public function test_format_logs_for_response_maps_full_log_payload(): void
    {
        $character = $this->createCharacter();
        $map = $this->createMap();
        $monster = $this->createMonster();
        $log = GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monster->id,
            'damage_dealt' => 20,
            'damage_taken' => 9,
            'victory' => true,
            'loot_dropped' => ['item' => 'horn'],
            'experience_gained' => 12,
            'copper_gained' => 6,
            'duration_seconds' => 4,
            'skills_used' => [9],
            'character_level' => 5,
            'character_class' => 'mage',
            'character_attack' => 30,
            'character_defense' => 12,
            'character_crit_rate' => 0.05,
            'character_crit_damage' => 1.6,
            'monster_level' => 3,
            'monster_hp' => 0,
            'monster_max_hp' => 25,
            'monster_attack' => 6,
            'monster_defense' => 2,
            'monster_experience' => 8,
            'monster_copper' => 4,
            'base_attack_damage' => 10,
            'skill_damage' => 5,
            'crit_damage' => 5,
            'aoe_damage' => 0,
            'total_damage_to_monsters' => 20,
            'monster_defense_reduction' => 0.1,
            'monster_counter_damage' => 9,
            'round_number' => 2,
            'monsters_alive_count' => 0,
            'monsters_killed_count' => 1,
            'difficulty_tier' => 0,
            'difficulty_multiplier' => 1.0,
        ]);
        $log->load(['monster', 'map']);

        $result = $this->service->formatLogsForResponse(collect([$log]));

        $this->assertCount(1, $result);
        $this->assertSame($log->id, $result[0]['id']);
        $this->assertSame($monster->name, $result[0]['monster']);
        $this->assertSame($map->name, $result[0]['map']);
        $this->assertSame(4, $result[0]['monster_copper']);
        $this->assertSame(20, $result[0]['total_damage_to_monsters']);
        $this->assertSame(1.0, (float) $result[0]['difficulty_multiplier']);
        $this->assertSame($log->created_at->toISOString(), $result[0]['created_at']);
    }

    private function createCharacter(array $attributes = []): GameCharacter
    {
        $user = User::factory()->create();

        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'CombatLogHero' . $user->id,
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

    private function createMap(array $attributes = []): GameMapDefinition
    {
        return GameMapDefinition::create(array_merge([
            'name' => 'Combat Log Map ' . uniqid(),
            'act' => 1,
            'monster_ids' => [],
            'background' => 'bg',
            'description' => 'combat log map',
            'is_active' => true,
        ], $attributes));
    }

    private function createMonster(array $attributes = []): GameMonsterDefinition
    {
        return GameMonsterDefinition::create(array_merge([
            'name' => 'Combat Log Monster',
            'type' => 'normal',
            'level' => 4,
            'hp_base' => 40,
            'attack_base' => 12,
            'defense_base' => 5,
            'experience_base' => 20,
            'drop_table' => [],
            'icon' => 'monster',
            'is_active' => true,
        ], $attributes));
    }
}
