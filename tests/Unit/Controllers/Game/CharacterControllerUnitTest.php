<?php

namespace Tests\Unit\Controllers\Game;

use App\Http\Controllers\Api\Game\CharacterController;
use App\Http\Requests\Game\AllocateStatsRequest;
use App\Http\Requests\Game\CreateCharacterRequest;
use App\Http\Requests\Game\DeleteCharacterRequest;
use App\Http\Requests\Game\UpdateDifficultyRequest;
use App\Models\Game\GameCharacter;
use App\Models\User;
use App\Services\Game\GameCharacterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class CharacterControllerUnitTest extends TestCase
{
    use RefreshDatabase;

    private GameCharacterService $characterService;

    private CharacterController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->characterService = Mockery::mock(GameCharacterService::class);
        $this->controller = new CharacterController($this->characterService);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_index_returns_character_list_payload(): void
    {
        $user = User::factory()->create();
        $payload = ['characters' => [['id' => 1]], 'total' => 1];

        $this->characterService->shouldReceive('getCharacterList')->once()->with($user->id)->andReturn($payload);

        $response = $this->controller->index($this->makeRequest($user));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $data['data']['total']);
        $this->assertSame(1, $data['data']['characters'][0]['id']);
    }

    public function test_show_passes_character_id_and_fills_default_fields(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->characterService->shouldReceive('getCharacterDetail')
            ->once()
            ->with($user->id, $character->id)
            ->andReturn(['character' => ['id' => $character->id]]);

        $response = $this->controller->show($this->makeRequest($user, ['character_id' => $character->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($character->id, $data['data']['character']['id']);
        $this->assertSame([], $data['data']['experience_table']);
        $this->assertSame(0, $data['data']['current_hp']);
    }

    public function test_show_uses_null_character_id_when_query_is_empty(): void
    {
        $user = User::factory()->create();

        $this->characterService->shouldReceive('getCharacterDetail')
            ->once()
            ->with($user->id, null)
            ->andReturn([
                'character' => null,
                'current_hp' => 88,
                'current_mana' => 44,
            ]);

        $response = $this->controller->show($this->makeRequest($user, ['character_id' => '']));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($data['data']['character']);
        $this->assertSame(88, $data['data']['current_hp']);
        $this->assertSame(44, $data['data']['current_mana']);
    }

    public function test_store_returns_created_character_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'name' => 'NewHero',
            'class' => 'mage',
            'gender' => 'female',
        ]);

        $this->characterService->shouldReceive('createCharacter')
            ->once()
            ->with($user->id, 'NewHero', 'mage', 'female')
            ->andReturn($character);

        $response = $this->controller->store($this->makeFormRequest(CreateCharacterRequest::class, $user, [
            'name' => 'NewHero',
            'class' => 'mage',
            'gender' => 'female',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('角色创建成功', $data['message']);
        $this->assertSame('NewHero', $data['data']['character']['name']);
        $this->assertArrayHasKey('combat_stats', $data['data']);
    }

    public function test_store_returns_service_error_message(): void
    {
        $user = User::factory()->create();

        $this->characterService->shouldReceive('createCharacter')
            ->once()
            ->with($user->id, 'BadHero', 'warrior', 'male')
            ->andThrow(new \RuntimeException('name taken'));

        $response = $this->controller->store($this->makeFormRequest(CreateCharacterRequest::class, $user, [
            'name' => 'BadHero',
            'class' => 'warrior',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('name taken', $data['message']);
    }

    public function test_destroy_deletes_character_successfully(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->characterService->shouldReceive('deleteCharacter')
            ->once()
            ->with($user->id, $character->id);

        $response = $this->controller->destroy($this->makeFormRequest(DeleteCharacterRequest::class, $user, [
            'character_id' => $character->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('角色已删除', $data['message']);
    }

    public function test_destroy_returns_service_error(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->characterService->shouldReceive('deleteCharacter')
            ->once()
            ->with($user->id, $character->id)
            ->andThrow(new \RuntimeException('delete denied'));

        $response = $this->controller->destroy($this->makeFormRequest(DeleteCharacterRequest::class, $user, [
            'character_id' => $character->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('delete denied', $data['message']);
    }

    public function test_allocate_stats_passes_all_stat_fields(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['character' => ['id' => $character->id], 'current_hp' => 120];

        $this->characterService->shouldReceive('allocateStats')
            ->once()
            ->with($user->id, $character->id, [
                'strength' => 2,
                'dexterity' => 1,
                'vitality' => 3,
                'energy' => 4,
            ])
            ->andReturn($payload);

        $response = $this->controller->allocateStats($this->makeFormRequest(AllocateStatsRequest::class, $user, [
            'character_id' => $character->id,
            'strength' => 2,
            'dexterity' => 1,
            'vitality' => 3,
            'energy' => 4,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('属性分配成功', $data['message']);
        $this->assertSame(120, $data['data']['current_hp']);
    }

    public function test_allocate_stats_returns_service_error(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->characterService->shouldReceive('allocateStats')
            ->once()
            ->with($user->id, $character->id, [
                'strength' => 0,
                'dexterity' => 0,
                'vitality' => 0,
                'energy' => 0,
            ])
            ->andThrow(new \RuntimeException('not enough points'));

        $response = $this->controller->allocateStats($this->makeFormRequest(AllocateStatsRequest::class, $user, [
            'character_id' => $character->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('not enough points', $data['message']);
    }

    public function test_update_difficulty_returns_updated_character(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['difficulty_tier' => 2]);

        $this->characterService->shouldReceive('updateDifficulty')
            ->once()
            ->with($user->id, 2, $character->id)
            ->andReturn($character);

        $response = $this->controller->updateDifficulty($this->makeFormRequest(UpdateDifficultyRequest::class, $user, [
            'character_id' => $character->id,
            'difficulty_tier' => 2,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('难度已更新', $data['message']);
        $this->assertSame(2, $data['data']['character']['difficulty_tier']);
    }

    public function test_update_difficulty_returns_service_error(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->characterService->shouldReceive('updateDifficulty')
            ->once()
            ->with($user->id, 9, $character->id)
            ->andThrow(new \RuntimeException('difficulty denied'));

        $response = $this->controller->updateDifficulty($this->makeFormRequest(UpdateDifficultyRequest::class, $user, [
            'character_id' => $character->id,
            'difficulty_tier' => 9,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('difficulty denied', $data['message']);
    }

    public function test_detail_returns_full_detail_payload(): void
    {
        $user = User::factory()->create();
        $payload = ['inventory' => [['id' => 7]], 'current_hp' => 100];

        $this->characterService->shouldReceive('getCharacterFullDetail')
            ->once()
            ->with($user->id, null)
            ->andReturn($payload);

        $response = $this->controller->detail($this->makeRequest($user));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(7, $data['data']['inventory'][0]['id']);
        $this->assertSame(100, $data['data']['current_hp']);
    }

    public function test_online_updates_last_online_timestamp(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['last_online' => null]);

        $response = $this->controller->online($this->makeRequest($user, ['character_id' => $character->id]));
        $data = json_decode($response->getContent(), true);
        $character->refresh();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($data['data']['last_online']);
        $this->assertNotNull($character->last_online);
    }

    public function test_online_returns_error_when_character_cannot_be_resolved(): void
    {
        $user = User::factory()->create();

        $response = $this->controller->online($this->makeRequest($user, ['character_id' => 999999]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('No query results for model [App\\Models\\Game\\GameCharacter].', $data['message']);
    }

    public function test_check_offline_rewards_returns_service_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['available' => true, 'experience' => 120, 'copper' => 60];

        $this->characterService->shouldReceive('checkOfflineRewards')
            ->once()
            ->with(Mockery::on(fn ($candidate) => $candidate instanceof GameCharacter && $candidate->is($character)))
            ->andReturn($payload);

        $response = $this->controller->checkOfflineRewards($this->makeRequest($user, ['character_id' => $character->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['data']['available']);
        $this->assertSame(120, $data['data']['experience']);
    }

    public function test_check_offline_rewards_returns_error_when_character_cannot_be_resolved(): void
    {
        $user = User::factory()->create();

        $response = $this->controller->checkOfflineRewards($this->makeRequest($user, ['character_id' => 999999]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('No query results for model [App\\Models\\Game\\GameCharacter].', $data['message']);
    }

    public function test_claim_offline_rewards_uses_level_up_message_when_applicable(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['experience' => 200, 'copper' => 80, 'level_up' => true, 'new_level' => 11];

        $this->characterService->shouldReceive('claimOfflineRewards')
            ->once()
            ->with(Mockery::on(fn ($candidate) => $candidate instanceof GameCharacter && $candidate->is($character)))
            ->andReturn($payload);

        $response = $this->controller->claimOfflineRewards($this->makeRequest($user, ['character_id' => $character->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('升级到了 11 级！', $data['message']);
        $this->assertSame(200, $data['data']['experience']);
    }

    public function test_claim_offline_rewards_uses_default_message_without_level_up(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['experience' => 0, 'copper' => 10, 'level_up' => false, 'new_level' => 10];

        $this->characterService->shouldReceive('claimOfflineRewards')
            ->once()
            ->with(Mockery::on(fn ($candidate) => $candidate instanceof GameCharacter && $candidate->is($character)))
            ->andReturn($payload);

        $response = $this->controller->claimOfflineRewards($this->makeRequest($user, ['character_id' => $character->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('离线奖励已领取', $data['message']);
        $this->assertSame(10, $data['data']['copper']);
    }

    public function test_claim_offline_rewards_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->characterService->shouldReceive('claimOfflineRewards')
            ->once()
            ->with(Mockery::on(fn ($candidate) => $candidate instanceof GameCharacter && $candidate->is($character)))
            ->andThrow(new \RuntimeException('claim failed'));

        $response = $this->controller->claimOfflineRewards($this->makeRequest($user, ['character_id' => $character->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('claim failed', $data['message']);
    }

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Hero' . $user->id . '-' . GameCharacter::query()->count(),
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 10,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'available_stat_points' => 5,
            'skill_points' => 0,
            'current_hp' => 100,
            'current_mana' => 50,
            'current_map_id' => 1,
            'is_fighting' => false,
            'difficulty_tier' => 1,
        ], $attributes));
    }

    private function makeRequest(User $user, array $payload = []): Request
    {
        $request = Request::create('/api/rpg/character', 'GET', $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * @param  class-string<FormRequest>  $class
     */
    private function makeFormRequest(string $class, User $user, array $payload = []): FormRequest
    {
        /** @var FormRequest $request */
        $request = $class::create('/api/rpg/character', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        return $request;
    }
}
