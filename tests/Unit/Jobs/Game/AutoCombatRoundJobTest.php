<?php

namespace Tests\Unit\Jobs\Game;

use App\Events\Game\GameCombatUpdate;
use App\Jobs\Game\AutoCombatRoundJob;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameMapDefinition;
use App\Models\User;
use App\Services\Game\GameCombatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class AutoCombatRoundJobTest extends TestCase
{
    use RefreshDatabase;

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
        $job = new AutoCombatRoundJob(1, [101, 102]);

        $this->assertInstanceOf(AutoCombatRoundJob::class, $job);
        $this->assertSame(1, $job->characterId);
        $this->assertSame([101, 102], $job->skillIds);
        $this->assertSame(30, $job->timeout);
    }

    public function test_redis_key_returns_correct_format(): void
    {
        $this->assertSame('rpg:combat:auto:42', AutoCombatRoundJob::redisKey(42));
        $this->assertSame('rpg:combat:auto:1', AutoCombatRoundJob::redisKey(1));
    }

    public function test_with_exponential_backoff(): void
    {
        $job = new AutoCombatRoundJob(1);

        $this->assertSame(1, $job->withExponentialBackoff(0));
        $this->assertSame(2, $job->withExponentialBackoff(1));
        $this->assertSame(4, $job->withExponentialBackoff(2));
        $this->assertSame(8, $job->withExponentialBackoff(3));
    }

    public function test_handle_returns_early_when_redis_key_not_set(): void
    {
        Redis::shouldReceive('get')
            ->with('rpg:combat:auto:999')
            ->once()
            ->andReturn(null);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldNotReceive('executeRound');
        $combatService->shouldNotReceive('shouldRefreshMonsters');

        $job = new AutoCombatRoundJob(999);
        $job->handle($combatService);
    }

    public function test_handle_returns_early_when_lock_not_acquired(): void
    {
        $key = 'rpg:combat:auto:1';
        $payload = json_encode(['skill_ids' => [101]]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->once()->andReturn(false);

        Cache::shouldReceive('lock')
            ->with('rpg:combat:lock:1', 35)
            ->once()
            ->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldNotReceive('executeRound');

        $job = new AutoCombatRoundJob(1);
        $job->handle($combatService);
    }

    public function test_handle_returns_early_when_redis_key_deleted_after_lock(): void
    {
        $key = 'rpg:combat:auto:1';
        $payload = json_encode(['skill_ids' => [101]]);

        Redis::shouldReceive('get')
            ->with($key)
            ->andReturn($payload, null);

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->once()->andReturn(true);

        Cache::shouldReceive('lock')
            ->with('rpg:combat:lock:1', 35)
            ->once()
            ->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldNotReceive('executeRound');

        $job = new AutoCombatRoundJob(1);
        $job->handle($combatService);
    }

    public function test_handle_deletes_redis_and_returns_when_character_not_found(): void
    {
        $key = 'rpg:combat:auto:99999';
        $payload = json_encode(['skill_ids' => [101]]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')
            ->with('rpg:combat:lock:99999', 35)
            ->once()
            ->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldNotReceive('executeRound');

        $job = new AutoCombatRoundJob(99999);
        $job->handle($combatService);
    }

    public function test_handle_executes_round_and_deletes_redis_on_defeat(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
            'current_hp' => 40,
            'current_mana' => 15,
        ]);

        $key = 'rpg:combat:auto:' . $character->id;
        $payload = json_encode(['skill_ids' => [101]]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')
            ->with('rpg:combat:lock:' . $character->id, 35)
            ->once()
            ->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->with(\Mockery::type(GameCharacter::class))->andReturn(false);
        $combatService->shouldReceive('executeRound')
            ->with(\Mockery::type(GameCharacter::class), [101])
            ->once()
            ->andReturn(['defeat' => true, 'victory' => false]);

        $job = new AutoCombatRoundJob($character->id, [101]);
        $job->handle($combatService);
    }

    public function test_handle_executes_round_and_deletes_redis_on_auto_stopped(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
        ]);

        $key = 'rpg:combat:auto:' . $character->id;
        $payload = json_encode(['skill_ids' => []]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')
            ->with('rpg:combat:lock:' . $character->id, 35)
            ->once()
            ->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->andReturn(false);
        $combatService->shouldReceive('executeRound')
            ->andReturn(['auto_stopped' => true, 'defeat' => false, 'victory' => false]);

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);
    }

    public function test_handle_removes_cancelled_skills_and_updates_redis(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter(['current_map_id' => $map->id]);

        $key = 'rpg:combat:auto:' . $character->id;
        $initialPayload = json_encode([
            'skill_ids' => [101, 102, 103],
            'cancelled_skill_ids' => [102],
        ]);
        $updatedPayload = json_encode([
            'skill_ids' => [101, 103],
            'cancelled_skill_ids' => [102],
        ]);

        Redis::shouldReceive('get')
            ->with($key)
            ->andReturn($initialPayload, $initialPayload, $updatedPayload);
        Redis::shouldReceive('set')
            ->with($key, $updatedPayload)
            ->once();
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->andReturn(false);
        $combatService->shouldReceive('executeRound')
            ->with(\Mockery::type(GameCharacter::class), [101, 103])
            ->once()
            ->andReturn(['defeat' => true]);

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);
    }

    public function test_handle_calls_broadcast_monsters_appear_when_should_refresh(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter(['current_map_id' => $map->id]);

        $key = 'rpg:combat:auto:' . $character->id;
        $payload = json_encode(['skill_ids' => [101]]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->with(\Mockery::type(GameCharacter::class))->once()->andReturn(true);
        $combatService->shouldReceive('broadcastMonstersAppear')
            ->with(\Mockery::type(GameCharacter::class), \Mockery::type(GameMapDefinition::class))
            ->once();
        $combatService->shouldReceive('executeRound')
            ->andReturn(['defeat' => true]);

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);
    }

    public function test_handle_uses_fresh_skill_ids_from_redis_before_execute(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter(['current_map_id' => $map->id]);

        $key = 'rpg:combat:auto:' . $character->id;
        $initialPayload = json_encode(['skill_ids' => [101]]);
        $freshPayload = json_encode(['skill_ids' => [102, 103]]);

        Redis::shouldReceive('get')->with($key)->andReturn($initialPayload, $freshPayload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->andReturn(false);
        $combatService->shouldReceive('executeRound')
            ->with(\Mockery::type(GameCharacter::class), [102, 103])
            ->once()
            ->andReturn(['defeat' => true]);

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);
    }

    public function test_handle_broadcasts_and_cleans_up_on_runtime_exception(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
            'is_fighting' => true,
        ]);

        $key = 'rpg:combat:auto:' . $character->id;
        $payload = json_encode(['skill_ids' => [101]]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->andReturn(false);
        $combatService->shouldReceive('executeRound')
            ->andThrow(new RuntimeException('Combat error'));

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);

        $character->refresh();
        $this->assertFalse($character->is_fighting);
    }

    public function test_handle_broadcasts_auto_stopped_event_on_exception(): void
    {
        \Illuminate\Support\Facades\Event::fake([GameCombatUpdate::class]);

        $map = $this->createMap();
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
            'is_fighting' => true,
            'current_hp' => 30,
        ]);

        $key = 'rpg:combat:auto:' . $character->id;
        $payload = json_encode(['skill_ids' => [101]]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->andReturn(false);
        $combatService->shouldReceive('executeRound')
            ->andThrow(new RuntimeException('Error'));

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);

        \Illuminate\Support\Facades\Event::assertDispatched(GameCombatUpdate::class, function (GameCombatUpdate $event) use ($character) {
            return $event->characterId === $character->id
                && $event->combatResult['auto_stopped'] === true
                && $event->combatResult['defeat'] === false
                && $event->combatResult['victory'] === false;
        });
    }

    public function test_handle_broadcasts_and_cleans_up_on_invalid_argument_exception(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter([
            'current_map_id' => $map->id,
            'is_fighting' => true,
        ]);

        $key = 'rpg:combat:auto:' . $character->id;
        $payload = json_encode(['skill_ids' => [101]]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->andReturn(false);
        $combatService->shouldReceive('executeRound')
            ->andThrow(new InvalidArgumentException('Invalid skill'));

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);

        $character->refresh();
        $this->assertFalse($character->is_fighting);
    }

    public function test_handle_normalizes_skill_ids_to_integers(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter(['current_map_id' => $map->id]);

        $key = 'rpg:combat:auto:' . $character->id;
        $payload = json_encode(['skill_ids' => ['101', '102', 103]]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->andReturn(false);
        $combatService->shouldReceive('executeRound')
            ->with(\Mockery::type(GameCharacter::class), [101, 102, 103])
            ->once()
            ->andReturn(['defeat' => true]);

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);
    }

    public function test_handle_handles_invalid_skill_ids_in_payload(): void
    {
        $map = $this->createMap();
        $character = $this->createCharacter(['current_map_id' => $map->id]);

        $key = 'rpg:combat:auto:' . $character->id;
        $payload = json_encode(['skill_ids' => 'not-an-array']);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->andReturn(false);
        $combatService->shouldReceive('executeRound')
            ->with(\Mockery::type(GameCharacter::class), [])
            ->once()
            ->andReturn(['defeat' => true]);

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);
    }

    public function test_handle_does_not_broadcast_monsters_appear_when_map_is_null(): void
    {
        $character = $this->createCharacter(['current_map_id' => null]);

        $key = 'rpg:combat:auto:' . $character->id;
        $payload = json_encode(['skill_ids' => [101]]);

        Redis::shouldReceive('get')->with($key)->andReturn($payload);
        Redis::shouldReceive('del')->with($key)->once();

        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')->andReturn($lock);

        $combatService = $this->mock(GameCombatService::class);
        $combatService->shouldReceive('shouldRefreshMonsters')->with(\Mockery::type(GameCharacter::class))->once()->andReturn(true);
        $combatService->shouldNotReceive('broadcastMonstersAppear');
        $combatService->shouldReceive('executeRound')->andReturn(['defeat' => true]);

        $job = new AutoCombatRoundJob($character->id);
        $job->handle($combatService);
    }
}
