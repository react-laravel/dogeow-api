<?php

namespace Tests\Feature\Controllers\Game;

use App\Jobs\Game\AutoCombatRoundJob;
use App\Models\Game\GameCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CombatControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
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
            'available_stat_points' => 0,
            'skill_points' => 0,
            'current_hp' => 100,
            'current_mana' => 50,
            'current_map_id' => 1,
            'is_fighting' => false,
            'difficulty_tier' => 1,
        ], $attributes));
    }

    public function test_can_get_combat_status(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        Redis::shouldReceive('get')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andReturn(null);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/combat/status?character_id=' . $character->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'is_fighting',
                'current_map',
                'combat_stats',
                'current_hp',
                'current_mana',
                'skill_cooldowns',
            ],
        ]);
        $response->assertJsonPath('data.is_fighting', false);
    }

    public function test_can_start_combat(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_map_id' => 1, 'is_fighting' => false]);

        Redis::shouldReceive('set')
            ->once()
            ->withArgs(function ($key, $value, $options) use ($character) {
                return str_ends_with($key, (string) $character->id)
                    && is_array($options)
                    && ($options['EX'] ?? null) === AutoCombatRoundJob::ttl()
                    && ($options['NX'] ?? false) === true;
            })
            ->andReturn(true);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/combat/start?character_id=' . $character->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'message',
            ],
        ]);
        Bus::assertDispatched(AutoCombatRoundJob::class);
    }

    public function test_start_rejects_dead_character(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'current_hp' => 0,
            'current_map_id' => 5,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/combat/start?character_id=' . $character->id);

        $response->assertStatus(422);
        $response->assertJsonPath('message', '角色已死亡，请先复活');
        Bus::assertNothingDispatched();
    }

    public function test_can_revive_dead_character(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'current_hp' => 0,
            'current_mana' => 25,
            'current_map_id' => 5,
            'is_fighting' => true,
        ]);

        Redis::shouldReceive('del')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andReturn(1);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/combat/revive?character_id=' . $character->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.message', '角色已复活并传送到新手村，请手动开始战斗');

        $character->refresh();
        $this->assertSame(1, $character->current_map_id);
        $this->assertSame($character->getCreationHp(), $character->current_hp);
        $this->assertSame(25, $character->current_mana);
        $this->assertFalse((bool) $character->is_fighting);
        Bus::assertNothingDispatched();
    }

    public function test_revive_rejects_alive_character(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_hp' => 100]);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/combat/revive?character_id=' . $character->id);

        $response->assertStatus(422);
        $response->assertJsonPath('message', '角色未处于死亡状态');
    }

    public function test_can_stop_combat(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['is_fighting' => true]);

        Redis::shouldReceive('del')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andReturn(1);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/combat/stop?character_id=' . $character->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'message',
            ],
        ]);
        $response->assertJsonPath('data.message', '自动战斗已停止');
    }

    public function test_can_get_combat_logs(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/combat/logs?character_id=' . $character->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'logs',
            ],
        ]);
        $this->assertIsArray($response->json('data.logs'));
    }

    public function test_can_get_combat_stats(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/combat/stats?character_id=' . $character->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'stats' => [
                    'total_battles',
                    'total_victories',
                    'total_defeats',
                    'total_damage_dealt',
                    'total_damage_taken',
                    'total_experience_gained',
                    'total_copper_gained',
                    'total_items_looted',
                ],
            ],
        ]);
    }

    public function test_can_update_potion_settings(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/combat/potion-settings?character_id=' . $character->id, [
                'auto_use_hp_potion' => true,
                'auto_use_mp_potion' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'character',
            ],
        ]);
        $response->assertJsonPath('data.character.auto_use_hp_potion', true);
        $response->assertJsonPath('data.character.auto_use_mp_potion', true);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/combat/status');

        $response->assertStatus(401);
    }
}
