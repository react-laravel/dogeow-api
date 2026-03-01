<?php

namespace Tests\Feature\Controllers\Game;

use App\Models\Game\GameCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/combat/status?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_start_combat(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_map_id' => 1, 'is_fighting' => false]);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/combat/start?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_stop_combat(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['is_fighting' => true]);

        $key = 'auto_combat:' . $character->id;
        Redis::del($key);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/combat/stop?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_get_combat_logs(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/combat/logs?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_get_combat_stats(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/combat/stats?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_update_potion_settings(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/combat/potion-settings?character_id=' . $character->id, [
                'auto_use_hp_potion' => true,
                'auto_use_mp_potion' => true,
                'hp_potion_threshold' => 30,
                'mp_potion_threshold' => 30,
            ]);

        $response->assertStatus(200);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/combat/status');

        $response->assertStatus(401);
    }
}
