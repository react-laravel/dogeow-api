<?php

namespace Tests\Unit\Jobs\Game;

use App\Events\Game\GameCombatUpdate;
use App\Jobs\Game\RefreshCombatMonstersJob;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameMapDefinition;
use App\Models\User;
use App\Services\Game\GameMonsterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RefreshCombatMonstersJobTest extends TestCase
{
    use RefreshDatabase;

    private function createCharacter(array $attributes = []): GameCharacter
    {
        $user = User::factory()->create();
        $character = GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'RefreshHero' . $user->id,
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
            'combat_monsters' => null,
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
            'name' => 'Test Map',
            'act' => 1,
            'monster_ids' => [],
            'background' => 'graveyard',
            'description' => 'Test',
            'is_active' => true,
        ], $attributes));
    }

    public function test_job_can_be_constructed(): void
    {
        $job = new RefreshCombatMonstersJob;

        $this->assertInstanceOf(RefreshCombatMonstersJob::class, $job);
        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    public function test_handle_does_nothing_when_no_fighting_characters(): void
    {
        $this->createCharacter(['is_fighting' => false, 'combat_monsters' => []]);

        $monsterService = $this->mock(GameMonsterService::class);
        $monsterService->shouldNotReceive('shouldRefreshMonsters');
        $monsterService->shouldNotReceive('generateNewMonsters');
        $monsterService->shouldNotReceive('formatMonstersForResponse');

        $job = new RefreshCombatMonstersJob;
        $job->handle($monsterService);
    }

    public function test_handle_skips_characters_without_combat_monsters(): void
    {
        $this->createCharacter([
            'is_fighting' => true,
            'combat_monsters' => null,
        ]);

        $monsterService = $this->mock(GameMonsterService::class);
        $monsterService->shouldNotReceive('shouldRefreshMonsters');

        $job = new RefreshCombatMonstersJob;
        $job->handle($monsterService);
    }

    public function test_handle_skips_when_should_refresh_returns_false(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter([
            'is_fighting' => true,
            'current_map_id' => $map->id,
            'combat_monsters' => [['id' => 1, 'name' => 'Slime']],
        ]);

        $monsterService = $this->mock(GameMonsterService::class);
        $monsterService->shouldReceive('shouldRefreshMonsters')
            ->with(\Mockery::type(GameCharacter::class))
            ->once()
            ->andReturn(false);
        $monsterService->shouldNotReceive('generateNewMonsters');
        $monsterService->shouldNotReceive('formatMonstersForResponse');

        $job = new RefreshCombatMonstersJob;
        $job->handle($monsterService);
    }

    public function test_handle_skips_when_character_has_no_map(): void
    {
        $character = $this->createCharacter([
            'is_fighting' => true,
            'current_map_id' => null,
            'combat_monsters' => [['id' => 1, 'name' => 'Slime']],
        ]);

        $monsterService = $this->mock(GameMonsterService::class);
        $monsterService->shouldReceive('shouldRefreshMonsters')
            ->with(\Mockery::type(GameCharacter::class))
            ->once()
            ->andReturn(true);
        $monsterService->shouldNotReceive('generateNewMonsters');
        $monsterService->shouldNotReceive('formatMonstersForResponse');

        $job = new RefreshCombatMonstersJob;
        $job->handle($monsterService);
    }

    public function test_handle_refreshes_and_broadcasts_when_should_refresh(): void
    {
        Event::fake([GameCombatUpdate::class]);

        $map = $this->createMap();
        $character = $this->createCharacter([
            'is_fighting' => true,
            'current_map_id' => $map->id,
            'current_hp' => 35,
            'current_mana' => 12,
            'combat_monsters' => [['id' => 1, 'name' => 'Slime', 'hp' => 5]],
        ]);

        $monsterService = $this->mock(GameMonsterService::class);
        $monsterService->shouldReceive('shouldRefreshMonsters')
            ->with(\Mockery::type(GameCharacter::class))
            ->once()
            ->andReturn(true);
        $monsterService->shouldReceive('generateNewMonsters')
            ->with(
                \Mockery::type(GameCharacter::class),
                \Mockery::type(GameMapDefinition::class),
                [['id' => 1, 'name' => 'Slime', 'hp' => 5]],
                true
            )
            ->once();
        $monsterService->shouldReceive('formatMonstersForResponse')
            ->with(\Mockery::type(GameCharacter::class))
            ->once()
            ->andReturn([
                'monsters' => [['id' => 2, 'name' => 'New Wolf', 'type' => 'normal']],
            ]);

        $job = new RefreshCombatMonstersJob;
        $job->handle($monsterService);

        Event::assertDispatched(GameCombatUpdate::class, function (GameCombatUpdate $event) use ($character) {
            $data = $event->combatResult;

            return $event->characterId === $character->id
                && $data['type'] === 'monsters_appear'
                && isset($data['monsters'])
                && $data['monsters'][0]['name'] === 'New Wolf'
                && $data['character']['current_hp'] === 35
                && $data['character']['current_mana'] === 12;
        });
    }

    public function test_handle_refreshes_multiple_characters(): void
    {
        Event::fake([GameCombatUpdate::class]);

        $map = $this->createMap();
        $char1 = $this->createCharacter([
            'is_fighting' => true,
            'current_map_id' => $map->id,
            'combat_monsters' => [['id' => 1]],
        ]);
        $char2 = $this->createCharacter([
            'is_fighting' => true,
            'current_map_id' => $map->id,
            'combat_monsters' => [['id' => 2]],
        ]);

        $monsterService = $this->mock(GameMonsterService::class);
        $monsterService->shouldReceive('shouldRefreshMonsters')
            ->andReturn(true, true);
        $monsterService->shouldReceive('generateNewMonsters')->twice();
        $monsterService->shouldReceive('formatMonstersForResponse')
            ->andReturn(['monsters' => []], ['monsters' => []]);

        $job = new RefreshCombatMonstersJob;
        $job->handle($monsterService);

        Event::assertDispatched(GameCombatUpdate::class, 2);
    }

    public function test_handle_passes_combat_monsters_to_generate(): void
    {
        Event::fake([GameCombatUpdate::class]);

        $map = $this->createMap();
        $character = $this->createCharacter([
            'is_fighting' => true,
            'current_map_id' => $map->id,
            'combat_monsters' => [],
        ]);

        $monsterService = $this->mock(GameMonsterService::class);
        $monsterService->shouldReceive('shouldRefreshMonsters')->andReturn(true);
        $monsterService->shouldReceive('generateNewMonsters')
            ->with(
                \Mockery::type(GameCharacter::class),
                \Mockery::type(GameMapDefinition::class),
                [],
                true
            )
            ->once();
        $monsterService->shouldReceive('formatMonstersForResponse')->andReturn(['monsters' => []]);

        $job = new RefreshCombatMonstersJob;
        $job->handle($monsterService);
    }

    public function test_handle_processes_only_characters_matching_criteria(): void
    {
        Event::fake([GameCombatUpdate::class]);

        $map = $this->createMap();
        $this->createCharacter([
            'is_fighting' => true,
            'current_map_id' => $map->id,
            'combat_monsters' => [['id' => 1]],
        ]);
        $this->createCharacter([
            'is_fighting' => false,
            'combat_monsters' => [['id' => 2]],
        ]);

        $monsterService = $this->mock(GameMonsterService::class);
        $monsterService->shouldReceive('shouldRefreshMonsters')->once()->andReturn(true);
        $monsterService->shouldReceive('generateNewMonsters')->once();
        $monsterService->shouldReceive('formatMonstersForResponse')->once()->andReturn(['monsters' => []]);

        $job = new RefreshCombatMonstersJob;
        $job->handle($monsterService);

        Event::assertDispatched(GameCombatUpdate::class, 1);
    }

    public function test_job_can_be_dispatched(): void
    {
        RefreshCombatMonstersJob::dispatch();

        $this->addToAssertionCount(1);
    }
}
