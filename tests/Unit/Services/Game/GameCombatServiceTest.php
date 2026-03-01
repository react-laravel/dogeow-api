<?php

namespace Tests\Unit\Services\Game;

use App\Events\Game\GameCombatUpdate;
use App\Events\Game\GameInventoryUpdate;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameCombatLog;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\User;
use App\Services\Game\CombatRoundProcessor;
use App\Services\Game\GameCombatLogService;
use App\Services\Game\GameCombatLootService;
use App\Services\Game\GameCombatService;
use App\Services\Game\GameInventoryService;
use App\Services\Game\GameMonsterService;
use App\Services\Game\GamePotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class GameCombatServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_refresh_monsters_delegates_to_monster_service(): void
    {
        $character = $this->createCharacter();
        $monsterService = Mockery::mock(GameMonsterService::class);
        $monsterService->shouldReceive('shouldRefreshMonsters')
            ->once()
            ->with($character)
            ->andReturn(true);

        $service = $this->makeService(monsterService: $monsterService);

        $this->assertTrue($service->shouldRefreshMonsters($character));
    }

    public function test_broadcast_monsters_appear_generates_monsters_and_dispatches_event(): void
    {
        Event::fake();

        $character = $this->createCharacter([
            'current_hp' => 33,
            'current_mana' => 14,
        ]);
        $map = $this->createMap();
        $monsterService = Mockery::mock(GameMonsterService::class);
        $monsterService->shouldReceive('generateNewMonsters')
            ->once()
            ->with($character, $map, [], true);
        $monsterService->shouldReceive('formatMonstersForResponse')
            ->once()
            ->with($character)
            ->andReturn([
                'monsters' => [['id' => 1, 'name' => 'Spawned Slime']],
            ]);

        $service = $this->makeService(monsterService: $monsterService);

        $service->broadcastMonstersAppear($character, $map);

        Event::assertDispatched(GameCombatUpdate::class, function (GameCombatUpdate $event) use ($character) {
            return $event->characterId === $character->id
                && $event->combatResult['type'] === 'monsters_appear'
                && $event->combatResult['monsters'][0]['name'] === 'Spawned Slime'
                && $event->combatResult['character']['current_hp'] === 33
                && $event->combatResult['character']['current_mana'] === 14;
        });
    }

    public function test_get_combat_status_returns_fight_state_from_current_monsters(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
            'is_fighting' => true,
            'current_hp' => 28,
            'current_mana' => 9,
            'combat_skill_cooldowns' => ['101' => 2],
            'combat_monsters' => [
                [
                    'id' => 11,
                    'name' => 'Dead Wolf',
                    'type' => 'normal',
                    'level' => 2,
                    'hp' => 0,
                    'max_hp' => 20,
                ],
                [
                    'id' => 12,
                    'name' => 'Alive Wolf',
                    'type' => 'elite',
                    'level' => 3,
                    'hp' => 15,
                    'max_hp' => 25,
                ],
            ],
        ]);

        $service = $this->makeService();
        $result = $service->getCombatStatus($character);

        $this->assertTrue($result['is_fighting']);
        $this->assertSame(28, $result['current_hp']);
        $this->assertSame(9, $result['current_mana']);
        $this->assertSame(['101' => 2], $result['skill_cooldowns']);
        $this->assertSame('Alive Wolf', $result['current_combat_monster']['name']);
        $this->assertCount(2, $result['current_combat_monsters']);
    }

    public function test_get_combat_status_falls_back_to_legacy_single_monster_fields(): void
    {
        $map = $this->createMap();
        $monster = $this->createMonster([
            'name' => 'Legacy Ogre',
            'type' => 'boss',
            'level' => 9,
        ]);
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
            'is_fighting' => true,
            'combat_monsters' => [],
            'combat_monster_id' => $monster->id,
            'combat_monster_hp' => 7,
            'combat_monster_max_hp' => 19,
        ]);

        $service = $this->makeService();
        $result = $service->getCombatStatus($character);

        $this->assertSame($monster->id, $result['current_combat_monster']['id']);
        $this->assertSame('Legacy Ogre', $result['current_combat_monster']['name']);
        $this->assertSame(7, $result['current_combat_monster']['hp']);
        $this->assertSame(19, $result['current_combat_monster']['max_hp']);
    }

    public function test_update_potion_settings_updates_only_requested_fields(): void
    {
        $character = $this->createCharacter([
            'auto_use_hp_potion' => false,
            'hp_potion_threshold' => 30,
            'auto_use_mp_potion' => false,
            'mp_potion_threshold' => 25,
        ]);

        $service = $this->makeService();
        $updated = $service->updatePotionSettings($character, [
            'auto_use_hp_potion' => true,
            'mp_potion_threshold' => 55,
        ]);

        $this->assertTrue($updated->fresh()->auto_use_hp_potion);
        $this->assertSame(30, $updated->fresh()->hp_potion_threshold);
        $this->assertFalse($updated->fresh()->auto_use_mp_potion);
        $this->assertSame(55, $updated->fresh()->mp_potion_threshold);
    }

    public function test_execute_round_requires_a_selected_map(): void
    {
        $character = $this->createCharacter(['current_map_id' => null]);
        $service = $this->makeService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('请先选择一个地图');
        $service->executeRound($character);
    }

    public function test_execute_round_auto_stops_when_character_hp_is_zero(): void
    {
        $character = $this->createCharacter([
            'current_map_id' => 999,
            'current_hp' => 0,
        ]);
        $service = $this->makeService();

        try {
            $service->executeRound($character);
            $this->fail('Expected zero-hp runtime exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('角色血量不足，已自动停止战斗', $e->getMessage());
        }

        $fresh = $character->fresh();
        $this->assertFalse($fresh->is_fighting);
        $this->assertNull($fresh->combat_monster_id);
        $this->assertNull($fresh->combat_monsters);
    }

    public function test_execute_round_throws_when_map_cannot_be_loaded_or_monster_missing(): void
    {
        $monsterService = Mockery::mock(GameMonsterService::class);
        $service = $this->makeService(monsterService: $monsterService);

        $characterWithoutMap = $this->createCharacter([
            'current_map_id' => 999999,
            'current_hp' => 10,
        ]);

        try {
            $service->executeRound($characterWithoutMap);
            $this->fail('Expected missing map exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('地图不存在', $e->getMessage());
        }

        $map = $this->createMap();
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
            'current_hp' => 10,
        ]);

        $monsterService->shouldReceive('prepareMonsterInfo')
            ->once()
            ->with($character, Mockery::type(GameMapDefinition::class))
            ->andReturn([null, null, null, 0, 0]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('当前战斗怪物不存在，已清除状态');
        $service->executeRound($character);
    }

    public function test_execute_round_handles_defeat_and_dispatches_updates(): void
    {
        Event::fake();

        $map = $this->createMap();
        $monster = $this->createMonster([
            'name' => 'Brute',
            'level' => 6,
            'type' => 'elite',
        ]);
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
            'current_hp' => 20,
            'current_mana' => 8,
            'combat_rounds' => 1,
            'combat_total_damage_dealt' => 10,
            'combat_total_damage_taken' => 3,
            'combat_started_at' => now()->subSeconds(30),
            'combat_monster_id' => $monster->id,
            'combat_monster_hp' => 30,
            'combat_monster_max_hp' => 30,
            'combat_monsters' => [['id' => $monster->id, 'hp' => 30, 'max_hp' => 30]],
        ]);

        $roundProcessor = Mockery::mock(CombatRoundProcessor::class);
        $monsterService = Mockery::mock(GameMonsterService::class);
        $potionService = Mockery::mock(GamePotionService::class);
        $lootService = Mockery::mock(GameCombatLootService::class);
        $combatLogService = Mockery::mock(GameCombatLogService::class);
        $inventoryService = Mockery::mock(GameInventoryService::class);

        $monsterService->shouldReceive('prepareMonsterInfo')
            ->once()
            ->andReturn([$monster, $monster->level, null, 30, 30]);
        $roundProcessor->shouldReceive('processOneRound')
            ->once()
            ->andReturn([
                'new_char_hp' => 0,
                'new_char_mana' => 5,
                'round_damage_dealt' => 12,
                'round_damage_taken' => 25,
                'new_skills_aggregated' => [301],
                'new_cooldowns' => ['301' => 2],
                'defeat' => true,
                'new_monster_hp' => 11,
            ]);
        $potionService->shouldReceive('tryAutoUsePotions')
            ->once()
            ->andReturn([]);
        $combatLogService->shouldReceive('createDefeatLog')
            ->once()
            ->andReturn($this->makeCombatLogModel(456));
        $inventoryService->shouldReceive('getInventoryForBroadcast')
            ->once()
            ->with(Mockery::type(GameCharacter::class))
            ->andReturn([
                'inventory' => [],
                'storage' => [],
                'equipment' => [],
                'inventory_size' => 100,
                'storage_size' => 50,
            ]);
        app()->instance(GameInventoryService::class, $inventoryService);

        $service = $this->makeService(
            roundProcessor: $roundProcessor,
            monsterService: $monsterService,
            potionService: $potionService,
            lootService: $lootService,
            combatLogService: $combatLogService,
        );

        $result = $service->executeRound($character);

        $this->assertFalse($result['victory']);
        $this->assertTrue($result['defeat']);
        $this->assertTrue($result['auto_stopped']);
        $this->assertSame(456, $result['combat_log_id']);
        $this->assertSame(0, $result['current_hp']);
        $this->assertSame([301], $result['skills_used']);
        $this->assertFalse($character->fresh()->is_fighting);
        $this->assertNull($character->fresh()->combat_monsters);

        Event::assertDispatched(GameCombatUpdate::class);
        Event::assertDispatched(GameInventoryUpdate::class);
    }

    public function test_execute_round_handles_victory_rewards_and_logs(): void
    {
        Event::fake();

        $map = $this->createMap();
        $monster = $this->createMonster([
            'name' => 'Ghoul',
            'level' => 4,
            'type' => 'normal',
        ]);
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
            'current_hp' => 30,
            'current_mana' => 12,
            'combat_monster_id' => $monster->id,
            'combat_monster_hp' => 26,
            'combat_monster_max_hp' => 26,
            'combat_monsters' => [[
                'id' => $monster->id,
                'name' => $monster->name,
                'type' => $monster->type,
                'level' => $monster->level,
                'hp' => 26,
                'max_hp' => 26,
                'position' => 1,
            ]],
        ]);

        $roundProcessor = Mockery::mock(CombatRoundProcessor::class);
        $monsterService = Mockery::mock(GameMonsterService::class);
        $potionService = Mockery::mock(GamePotionService::class);
        $lootService = Mockery::mock(GameCombatLootService::class);
        $combatLogService = Mockery::mock(GameCombatLogService::class);
        $inventoryService = Mockery::mock(GameInventoryService::class);

        $baseRoundResult = [
            'new_char_hp' => 21,
            'new_char_mana' => 9,
            'round_damage_dealt' => 18,
            'round_damage_taken' => 6,
            'new_skills_aggregated' => [401],
            'new_cooldowns' => ['401' => 1],
            'defeat' => false,
            'has_alive_monster' => false,
            'new_monster_hp' => 0,
            'new_monster_max_hp' => 26,
            'experience_gained' => 13,
            'copper_gained' => 7,
            'loot' => ['seed' => 'base'],
            'skills_used_this_round' => [401],
            'skill_target_positions' => [1],
            'monsters_updated' => [[
                'id' => $monster->id,
                'name' => $monster->name,
                'type' => $monster->type,
                'level' => $monster->level,
                'hp' => 0,
                'max_hp' => 26,
                'position' => 1,
            ]],
        ];

        $monsterService->shouldReceive('prepareMonsterInfo')
            ->once()
            ->andReturn([$monster, $monster->level, null, 26, 26]);
        $roundProcessor->shouldReceive('processOneRound')
            ->once()
            ->andReturn($baseRoundResult);
        $potionService->shouldReceive('tryAutoUsePotions')
            ->once()
            ->andReturn([]);
        $monsterService->shouldReceive('tryAddNewMonsters')
            ->once()
            ->andReturnUsing(fn ($characterArg, $mapArg, array $roundResult) => $roundResult);
        $lootService->shouldReceive('distributeRewards')
            ->once()
            ->andReturn(['experience_gained' => 13, 'copper_gained' => 7]);
        $lootService->shouldReceive('processDeathLoot')
            ->once()
            ->andReturn(['item' => 'bone', 'potion' => 'hp']);
        $monsterService->shouldReceive('formatMonstersForResponse')
            ->once()
            ->andReturn([
                'monsters' => [[
                    'id' => $monster->id,
                    'name' => $monster->name,
                    'type' => $monster->type,
                    'level' => $monster->level,
                    'hp' => 0,
                    'max_hp' => 26,
                ]],
                'first_alive_monster' => [
                    'id' => $monster->id,
                    'name' => $monster->name,
                    'type' => $monster->type,
                    'level' => $monster->level,
                    'hp' => 0,
                    'max_hp' => 26,
                ],
            ]);
        $combatLogService->shouldReceive('createRoundLog')
            ->once()
            ->andReturn($this->makeCombatLogModel(123));
        $inventoryService->shouldReceive('getInventoryForBroadcast')
            ->once()
            ->with(Mockery::type(GameCharacter::class))
            ->andReturn([
                'inventory' => [],
                'storage' => [],
                'equipment' => [],
                'inventory_size' => 100,
                'storage_size' => 50,
            ]);
        app()->instance(GameInventoryService::class, $inventoryService);

        $service = $this->makeService(
            roundProcessor: $roundProcessor,
            monsterService: $monsterService,
            potionService: $potionService,
            lootService: $lootService,
            combatLogService: $combatLogService,
        );

        $result = $service->executeRound($character);

        $this->assertTrue($result['victory']);
        $this->assertFalse($result['defeat']);
        $this->assertSame(13, $result['experience_gained']);
        $this->assertSame(7, $result['copper_gained']);
        $this->assertSame('bone', $result['loot']['item']);
        $this->assertSame('hp', $result['loot']['potion']);
        $this->assertSame(7, $result['loot']['copper']);
        $this->assertSame(123, $result['combat_log_id']);
        $this->assertSame(1, $character->fresh()->combat_rounds);
        $this->assertSame(21, $character->fresh()->current_hp);
        $this->assertSame(9, $character->fresh()->current_mana);

        Event::assertDispatched(GameCombatUpdate::class);
        Event::assertDispatched(GameInventoryUpdate::class);
    }

    public function test_combat_log_methods_delegate_to_combat_log_service(): void
    {
        $character = $this->createCharacter();
        $combatLogService = Mockery::mock(GameCombatLogService::class);
        $combatLogService->shouldReceive('getCombatLogs')->once()->with($character)->andReturn(['logs' => ['a']]);
        $combatLogService->shouldReceive('getCombatLogDetail')->once()->with($character, 77)->andReturn(['log' => ['id' => 77]]);
        $combatLogService->shouldReceive('getCombatStats')->once()->with($character)->andReturn(['wins' => 3]);

        $service = $this->makeService(combatLogService: $combatLogService);

        $this->assertSame(['logs' => ['a']], $service->getCombatLogs($character));
        $this->assertSame(['log' => ['id' => 77]], $service->getCombatLogDetail($character, 77));
        $this->assertSame(['wins' => 3], $service->getCombatStats($character));
    }

    private function makeService(
        ?CombatRoundProcessor $roundProcessor = null,
        ?GameMonsterService $monsterService = null,
        ?GamePotionService $potionService = null,
        ?GameCombatLootService $lootService = null,
        ?GameCombatLogService $combatLogService = null
    ): GameCombatService {
        return new GameCombatService(
            $roundProcessor ?? Mockery::mock(CombatRoundProcessor::class),
            $monsterService ?? Mockery::mock(GameMonsterService::class),
            $potionService ?? Mockery::mock(GamePotionService::class),
            $lootService ?? Mockery::mock(GameCombatLootService::class),
            $combatLogService ?? Mockery::mock(GameCombatLogService::class),
        );
    }

    private function createCharacter(array $attributes = []): GameCharacter
    {
        $user = User::factory()->create();
        $character = GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'CombatHero' . $user->id,
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
            'current_hp' => 40,
            'current_mana' => 15,
            'discovered_items' => [],
            'discovered_monsters' => [],
        ], $attributes));

        foreach (config('game.slots') as $slot) {
            GameEquipment::create([
                'character_id' => $character->id,
                'slot' => $slot,
                'item_id' => null,
            ]);
        }

        return $character;
    }

    private function createMap(array $attributes = []): GameMapDefinition
    {
        return GameMapDefinition::create(array_merge([
            'name' => '墓园',
            'act' => 1,
            'monster_ids' => [],
            'background' => 'graveyard',
            'description' => 'Test combat map',
            'is_active' => true,
        ], $attributes));
    }

    private function createMonster(array $attributes = []): GameMonsterDefinition
    {
        return GameMonsterDefinition::create(array_merge([
            'name' => 'Combat Slime',
            'type' => 'normal',
            'level' => 1,
            'hp_base' => 20,
            'attack_base' => 5,
            'defense_base' => 2,
            'experience_base' => 10,
            'drop_table' => [],
            'icon' => 'slime',
            'is_active' => true,
        ], $attributes));
    }

    private function makeCombatLogModel(int $id): GameCombatLog
    {
        $log = new GameCombatLog;
        $log->id = $id;

        return $log;
    }
}
