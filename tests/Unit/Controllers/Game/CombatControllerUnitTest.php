<?php

namespace Tests\Unit\Controllers\Game;

use App\Exceptions\GameException;
use App\Http\Controllers\Api\Game\CombatController;
use App\Jobs\Game\AutoCombatRoundJob;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use App\Services\Game\GameCombatService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class CombatControllerUnitTest extends TestCase
{
    private GameCombatService $combatService;

    private CombatController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->combatService = Mockery::mock(GameCombatService::class);
        $this->controller = new CombatController($this->combatService);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_status_returns_combat_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['is_fighting' => false, 'monster' => null];

        $this->combatService->shouldReceive('syncCombatStatusWithRedis')->once()->with($this->sameCharacter($character));
        $this->combatService->shouldReceive('getCombatStatus')->once()->with($this->sameCharacter($character))->andReturn($payload);

        $response = $this->controller->status($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(false, $data['data']['is_fighting']);
    }

    public function test_status_resets_stale_fighting_flag_when_auto_combat_key_is_missing(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['is_fighting' => true]);
        $payload = ['is_fighting' => false, 'monster' => null];

        $this->combatService->shouldReceive('syncCombatStatusWithRedis')
            ->once()
            ->with($this->sameCharacter($character))
            ->andReturnUsing(function (GameCharacter $target): void {
                $target->update(['is_fighting' => false]);
            });
        $this->combatService->shouldReceive('getCombatStatus')->once()->with($this->sameCharacter($character))->andReturn($payload);

        $response = $this->controller->status($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);
        $character->refresh();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse((bool) $character->is_fighting);
        $this->assertSame(false, $data['data']['is_fighting']);
    }

    public function test_status_restores_fighting_flag_when_auto_combat_key_exists(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['is_fighting' => false]);
        $payload = ['is_fighting' => true, 'monster' => null];

        $this->combatService->shouldReceive('syncCombatStatusWithRedis')
            ->once()
            ->with($this->sameCharacter($character))
            ->andReturnUsing(function (GameCharacter $target): void {
                $target->update(['is_fighting' => true]);
            });
        $this->combatService->shouldReceive('getCombatStatus')->once()->with($this->sameCharacter($character))->andReturn($payload);

        $response = $this->controller->status($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);
        $character->refresh();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue((bool) $character->is_fighting);
        $this->assertSame(true, $data['data']['is_fighting']);
    }

    public function test_status_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->combatService->shouldReceive('syncCombatStatusWithRedis')->once()->with($this->sameCharacter($character));
        $this->combatService->shouldReceive('getCombatStatus')->once()->with($this->sameCharacter($character))->andThrow(new \RuntimeException('status boom'));

        $response = $this->controller->status($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('获取战斗状态失败，请稍后重试', $data['message']);
    }

    public function test_start_rejects_dead_character(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'current_hp' => 0,
            'current_mana' => 25,
            'current_map_id' => 5,
            'is_fighting' => false,
        ]);

        $response = $this->controller->start($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);
        $character->refresh();

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('角色已死亡，请先复活', $data['message']);
        $this->assertSame(5, $character->current_map_id);
        $this->assertSame(0, $character->current_hp);
        Bus::assertNothingDispatched();
    }

    public function test_revive_revives_dead_character_without_dispatching_combat(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'current_hp' => 0,
            'current_mana' => 25,
            'current_map_id' => 5,
            'is_fighting' => false,
        ]);

        $this->combatService->shouldReceive('reviveCharacter')
            ->once()
            ->with($this->sameCharacter($character))
            ->andReturnUsing(function (GameCharacter $target) {
                $target->update([
                    'current_hp' => $target->getCreationHp(),
                    'current_map_id' => 1,
                    'is_fighting' => false,
                ]);

                return $target->fresh();
            });

        $response = $this->controller->revive($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('角色已复活并传送到新手村，请手动开始战斗', $data['data']['message']);
        Bus::assertNothingDispatched();
    }

    public function test_revive_rejects_alive_character(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_hp' => 100]);

        $this->combatService->shouldReceive('reviveCharacter')
            ->once()
            ->with($this->sameCharacter($character))
            ->andThrow(GameException::invalidOperation('角色未处于死亡状态'));

        $response = $this->controller->revive($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('角色未处于死亡状态', $data['message']);
    }

    public function test_start_returns_success_when_auto_combat_is_already_running(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $key = AutoCombatRoundJob::redisKey($character->id);

        Redis::shouldReceive('get')
            ->once()
            ->with($key)
            ->andReturn(json_encode(['skill_ids' => null]));

        $response = $this->controller->start($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('自动战斗已在进行中，结果将通过 WebSocket 推送', $data['data']['message']);
        Bus::assertNothingDispatched();
    }

    public function test_start_rejects_when_auto_combat_acquire_fails_without_payload(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $key = AutoCombatRoundJob::redisKey($character->id);

        Redis::shouldReceive('get')
            ->once()
            ->with($key)
            ->andReturn(null);

        // Redis::set with NX returns false when key already exists
        Redis::shouldReceive('set')
            ->once()
            ->with($key, Mockery::any(), 'EX', AutoCombatRoundJob::ttl(), 'NX')
            ->andReturn(false);

        $response = $this->controller->start($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('自动战斗已在运行中，请先停止当前战斗', $data['message']);
        Bus::assertNothingDispatched();
    }

    public function test_start_dispatches_auto_combat_job_with_normalized_skill_ids(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $request = $this->makeRequest($user, $character, ['skill_ids' => ['2', '7']]);
        $key = AutoCombatRoundJob::redisKey($character->id);
        $payload = json_encode(['skill_ids' => [2, 7]]);

        Redis::shouldReceive('get')->once()->with($key)->andReturn(null);

        // Redis::set with NX returns true when key is successfully set
        Redis::shouldReceive('set')
            ->once()
            ->with($key, $payload, 'EX', AutoCombatRoundJob::ttl(), 'NX')
            ->andReturn(true);

        $response = $this->controller->start($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('自动战斗已开始，结果将通过 WebSocket 推送', $data['data']['message']);
        Bus::assertDispatched(AutoCombatRoundJob::class, function (AutoCombatRoundJob $job) use ($character): bool {
            return $job->characterId === $character->id && $job->skillIds === [2, 7];
        });
    }

    public function test_start_persists_null_skill_ids_when_request_does_not_include_them(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $key = AutoCombatRoundJob::redisKey($character->id);
        $payload = json_encode(['skill_ids' => null]);

        Redis::shouldReceive('get')->once()->with($key)->andReturn(null);

        // Redis::set with NX returns true when key is successfully set
        Redis::shouldReceive('set')
            ->once()
            ->with($key, $payload, 'EX', AutoCombatRoundJob::ttl(), 'NX')
            ->andReturn(true);

        $response = $this->controller->start($this->makeRequest($user, $character));

        $this->assertSame(200, $response->getStatusCode());
        Bus::assertDispatched(AutoCombatRoundJob::class, function (AutoCombatRoundJob $job) use ($character): bool {
            return $job->characterId === $character->id && $job->skillIds === null;
        });
    }

    public function test_start_returns_fallback_error_when_exception_message_is_empty(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $key = AutoCombatRoundJob::redisKey($character->id);

        Redis::shouldReceive('get')->once()->with($key)->andReturn(null);

        Redis::shouldReceive('set')
            ->once()
            ->with($key, Mockery::any(), 'EX', AutoCombatRoundJob::ttl(), 'NX')
            ->andThrow(new \RuntimeException(''));

        $response = $this->controller->start($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('开始战斗失败，请稍后重试', $data['message']);
        Bus::assertNothingDispatched();
    }

    public function test_stop_clears_running_state(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['is_fighting' => true]);

        Redis::shouldReceive('del')->once()->with(AutoCombatRoundJob::redisKey($character->id))->andReturn(1);

        $response = $this->controller->stop($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);
        $character->refresh();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('自动战斗已停止', $data['data']['message']);
        $this->assertFalse((bool) $character->is_fighting);
    }

    public function test_stop_returns_error_when_redis_delete_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['is_fighting' => true]);

        Redis::shouldReceive('del')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andThrow(new \RuntimeException('stop boom'));

        $response = $this->controller->stop($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('停止战斗失败，请稍后重试', $data['message']);
    }

    public function test_logs_returns_service_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['logs' => [['id' => 9]]];

        $this->combatService->shouldReceive('getCombatLogs')->once()->with($this->sameCharacter($character))->andReturn($payload);

        $response = $this->controller->logs($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(9, $data['data']['logs'][0]['id']);
    }

    public function test_logs_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->combatService->shouldReceive('getCombatLogs')
            ->once()
            ->with($this->sameCharacter($character))
            ->andThrow(new \RuntimeException('logs boom'));

        $response = $this->controller->logs($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('获取战斗日志失败，请稍后重试', $data['message']);
    }

    public function test_log_detail_returns_detailed_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = [
            'log' => [
                'id' => 15,
                'map' => ['id' => 2, 'name' => '新手村'],
                'monster' => ['id' => 3, 'name' => '史莱姆'],
                'damage_detail' => ['total' => 55],
            ],
        ];

        $this->combatService->shouldReceive('getCombatLogDetail')
            ->once()
            ->with($this->sameCharacter($character), 15)
            ->andReturn($payload);

        $response = $this->controller->logDetail($this->makeRequest($user, $character), 15);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(15, $data['data']['log']['id']);
        $this->assertSame('新手村', $data['data']['log']['map']['name']);
        $this->assertSame('史莱姆', $data['data']['log']['monster']['name']);
        $this->assertSame(55, $data['data']['log']['damage_detail']['total']);
    }

    public function test_log_detail_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->combatService->shouldReceive('getCombatLogDetail')
            ->once()
            ->with($this->sameCharacter($character), 999999)
            ->andThrow(new \RuntimeException('detail boom'));

        $response = $this->controller->logDetail($this->makeRequest($user, $character), 999999);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('获取战斗日志详情失败，请稍后重试', $data['message']);
    }

    public function test_stats_returns_service_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['stats' => ['total_battles' => 4]];

        $this->combatService->shouldReceive('getCombatStats')->once()->with($this->sameCharacter($character))->andReturn($payload);

        $response = $this->controller->stats($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(4, $data['data']['stats']['total_battles']);
    }

    public function test_stats_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->combatService->shouldReceive('getCombatStats')
            ->once()
            ->with($this->sameCharacter($character))
            ->andThrow(new \RuntimeException('stats boom'));

        $response = $this->controller->stats($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('获取战斗统计失败，请稍后重试', $data['message']);
    }

    public function test_update_skills_requires_active_auto_combat(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        Redis::shouldReceive('get')->once()->with(AutoCombatRoundJob::redisKey($character->id))->andReturn(null);

        $response = $this->controller->updateSkills($this->makeRequest($user, $character, ['skill_ids' => [3, 8]]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('当前没有进行中的自动战斗', $data['message']);
    }

    public function test_update_skills_updates_redis_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $key = AutoCombatRoundJob::redisKey($character->id);
        $existingPayload = json_encode([
            'skill_ids' => [1],
            'cancelled_skill_ids' => [9],
            'round' => 2,
        ]);
        $expectedPayload = json_encode([
            'skill_ids' => [5, 11],
            'cancelled_skill_ids' => [],
            'round' => 2,
        ]);

        Redis::shouldReceive('get')->once()->with($key)->andReturn($existingPayload);
        Redis::shouldReceive('setex')
            ->once()
            ->with($key, AutoCombatRoundJob::ttl(), $expectedPayload)
            ->andReturnTrue();

        $response = $this->controller->updateSkills($this->makeRequest($user, $character, ['skill_ids' => ['5', '11']]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('技能配置已更新', $data['message']);
        $this->assertSame([5, 11], $data['data']['skill_ids']);
    }

    public function test_update_skills_handles_invalid_redis_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $key = AutoCombatRoundJob::redisKey($character->id);
        $expectedPayload = json_encode([
            'skill_ids' => [5, 11],
            'cancelled_skill_ids' => [],
        ]);

        Redis::shouldReceive('get')->once()->with($key)->andReturn('invalid-json');
        Redis::shouldReceive('setex')
            ->once()
            ->with($key, AutoCombatRoundJob::ttl(), $expectedPayload)
            ->andReturnTrue();

        $response = $this->controller->updateSkills($this->makeRequest($user, $character, ['skill_ids' => ['5', '11']]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('技能配置已更新', $data['message']);
        $this->assertSame([5, 11], $data['data']['skill_ids']);
    }

    public function test_update_skills_returns_error_when_redis_fails(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        Redis::shouldReceive('get')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andThrow(new \RuntimeException('redis boom'));

        $response = $this->controller->updateSkills($this->makeRequest($user, $character, ['skill_ids' => [3, 8]]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('更新技能配置失败，请稍后重试', $data['message']);
    }

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Combat Hero ' . $user->id . '-' . GameCharacter::query()->count(),
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 10,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'available_stat_points' => 0,
            'skill_points' => 0,
            'current_hp' => 100,
            'current_mana' => 50,
            'current_map_id' => 1,
            'is_fighting' => false,
            'difficulty_tier' => 1,
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
            'icon' => 'item',
            'description' => 'Basic item definition',
            'is_active' => true,
            'buy_price' => 100,
            'sell_price' => 50,
        ], $attributes));
    }

    private function createItem(GameCharacter $character, GameItemDefinition $definition, array $attributes = []): GameItem
    {
        return GameItem::create(array_merge([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $definition->base_stats,
            'affixes' => [],
            'is_in_storage' => false,
            'is_equipped' => false,
            'quantity' => 1,
            'slot_index' => 0,
            'sockets' => 0,
            'sell_price' => 20,
        ], $attributes));
    }

    private function sameCharacter(GameCharacter $character): mixed
    {
        return Mockery::on(static fn ($candidate): bool => $candidate instanceof GameCharacter && $candidate->is($character));
    }

    private function makeRequest(User $user, GameCharacter $character, array $payload = []): Request
    {
        $this->actingAs($user);

        $request = Request::create('/api/rpg/combat', 'POST', array_merge([
            'character_id' => $character->id,
        ], $payload));
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * @param  class-string<FormRequest>  $class
     */
    private function makeValidatedFormRequest(string $class, User $user, GameCharacter $character, array $payload = []): FormRequest
    {
        $this->actingAs($user);

        /** @var FormRequest $request */
        $request = $class::create('/api/rpg/combat', 'POST', array_merge([
            'character_id' => $character->id,
        ], $payload));
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        return $request;
    }
}
