<?php

namespace Tests\Feature\Controllers\Game;

use App\Events\Game\GameCombatUpdate;
use App\Jobs\Game\AutoCombatRoundJob;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MapControllerTest extends TestCase
{
    use RefreshDatabase;

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
            'available_stat_points' => 0,
            'skill_points' => 0,
            'current_hp' => 100,
            'current_mana' => 50,
            'current_map_id' => 1,
            'is_fighting' => false,
            'difficulty_tier' => 1,
        ], $attributes));
    }

    private function createMapDefinition(array $attributes = []): GameMapDefinition
    {
        return GameMapDefinition::create(array_merge([
            'name' => 'Newbie Village',
            'act' => 1,
            'level_range' => '1-5',
            'required_level' => 1,
            'is_active' => true,
            'monster_ids' => [],
        ], $attributes));
    }

    private function createMonsterDefinition(array $attributes = []): GameMonsterDefinition
    {
        return GameMonsterDefinition::create(array_merge([
            'name' => 'Test Monster',
            'type' => 'normal',
            'level' => 1,
            'hp_base' => 20,
            'attack_base' => 5,
            'defense_base' => 2,
            'experience_base' => 10,
            'drop_table' => [],
            'icon' => 'monster.png',
            'is_active' => true,
        ], $attributes));
    }

    public function test_can_get_all_maps(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $wolf = $this->createMonsterDefinition(['name' => 'Wolf']);
        $goblin = $this->createMonsterDefinition(['name' => 'Goblin']);
        $this->createMapDefinition(['monster_ids' => [$wolf->id]]);
        $this->createMapDefinition(['name' => 'Cave', 'monster_ids' => [$goblin->id]]);

        DB::enableQueryLog();

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/maps?character_id=' . $character->id);

        $monsterQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'game_monster_definitions'))
            ->count();

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.maps')
            ->assertJsonPath('data.maps.0.monsters.0.id', $wolf->id)
            ->assertJsonPath('data.maps.1.monsters.0.id', $goblin->id);
        $this->assertSame(1, $monsterQueries);
    }

    public function test_can_enter_map(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_map_id' => 1, 'is_fighting' => false]);
        $map = $this->createMapDefinition();

        Redis::shouldReceive('del')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andReturn(1);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/maps/' . $map->id . '/enter?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_enter_map_replaces_monsters_from_previous_map(): void
    {
        Event::fake([GameCombatUpdate::class]);

        $user = User::factory()->create();
        Redis::shouldReceive('del')->once()->andReturn(1);

        $deer = $this->createMonsterDefinition(['name' => 'Deer']);
        $goblin = $this->createMonsterDefinition(['name' => 'Goblin']);
        $forest = $this->createMapDefinition(['monster_ids' => [$deer->id]]);
        $goblinDen = $this->createMapDefinition(['name' => 'Goblin Den', 'monster_ids' => [$goblin->id]]);
        $character = $this->createCharacter($user, [
            'current_map_id' => $forest->id,
            'is_fighting' => true,
            'combat_monsters_refreshed_at' => now(),
            'combat_monsters' => [
                [
                    'id' => $deer->id,
                    'name' => $deer->name,
                    'type' => 'normal',
                    'level' => 1,
                    'hp' => 20,
                    'max_hp' => 20,
                    'position' => 0,
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/maps/' . $goblinDen->id . '/enter?character_id=' . $character->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.monsters.0.id', $goblin->id);

        $character->refresh();
        $this->assertSame($goblinDen->id, $character->current_map_id);
        $aliveMonsterIds = array_values(array_filter(array_map(
            static fn ($monster) => is_array($monster) && ($monster['hp'] ?? 0) > 0 ? (int) $monster['id'] : null,
            $character->combat_monsters ?? []
        )));
        $this->assertNotEmpty($aliveMonsterIds);
        $this->assertSame([$goblin->id], array_values(array_unique($aliveMonsterIds)));

        Event::assertDispatched(GameCombatUpdate::class);
    }

    public function test_can_teleport_to_map(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_map_id' => 1, 'is_fighting' => false]);
        $map = $this->createMapDefinition();

        Redis::shouldReceive('del')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andReturn(1);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/maps/' . $map->id . '/teleport?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_enter_map_when_dead_does_not_start_combat(): void
    {
        Event::fake([GameCombatUpdate::class]);

        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'current_map_id' => 1,
            'current_hp' => 0,
            'is_fighting' => false,
        ]);
        $map = $this->createMapDefinition();

        Redis::shouldReceive('del')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andReturn(1);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/maps/' . $map->id . '/enter?character_id=' . $character->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.character.current_hp', 0)
            ->assertJsonPath('data.monsters', []);

        $character->refresh();
        $this->assertFalse($character->is_fighting);
        $this->assertSame($map->id, $character->current_map_id);

        Event::assertNotDispatched(GameCombatUpdate::class);
    }

    public function test_teleport_from_death_revives_and_starts_combat(): void
    {
        Event::fake([GameCombatUpdate::class]);

        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'current_map_id' => 1,
            'current_hp' => 0,
            'is_fighting' => false,
        ]);
        $map = $this->createMapDefinition();

        Redis::shouldReceive('del')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andReturn(1);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/maps/' . $map->id . '/teleport?character_id=' . $character->id);

        $response->assertStatus(200);

        $character->refresh();
        $this->assertTrue($character->is_fighting);
        $this->assertGreaterThan(0, $character->current_hp);
        $this->assertSame($map->id, $character->current_map_id);

        Event::assertDispatched(GameCombatUpdate::class);
    }

    public function test_teleport_when_dead_and_still_fighting_does_not_start_combat(): void
    {
        Event::fake([GameCombatUpdate::class]);

        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'current_map_id' => 1,
            'current_hp' => 0,
            'is_fighting' => true,
        ]);
        $map = $this->createMapDefinition();

        Redis::shouldReceive('del')
            ->once()
            ->with(AutoCombatRoundJob::redisKey($character->id))
            ->andReturn(1);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/maps/' . $map->id . '/teleport?character_id=' . $character->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.character.current_hp', 0)
            ->assertJsonPath('data.monsters', []);

        $character->refresh();
        $this->assertFalse($character->is_fighting);

        Event::assertNotDispatched(GameCombatUpdate::class);
    }

    public function test_enter_map_clears_auto_combat_redis_key_so_combat_can_restart(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_map_id' => 1, 'is_fighting' => true]);
        $map = $this->createMapDefinition(['name' => 'Another Map']);
        $key = AutoCombatRoundJob::redisKey($character->id);

        Redis::shouldReceive('del')->once()->with($key)->andReturn(1);

        $this->actingAs($user)
            ->postJson('/api/rpg/maps/' . $map->id . '/enter?character_id=' . $character->id)
            ->assertStatus(200);
    }

    public function test_can_get_current_map(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_map_id' => 1]);
        $this->createMapDefinition();

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/maps/current?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_returns_null_when_no_current_map(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_map_id' => null]);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/maps/current?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/maps');

        $response->assertStatus(401);
    }
}
