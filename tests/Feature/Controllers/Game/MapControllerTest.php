<?php

namespace Tests\Feature\Controllers\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'monsters' => [],
        ], $attributes));
    }

    public function test_can_get_all_maps(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $this->createMapDefinition();

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/maps?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_enter_map(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_map_id' => 1, 'is_fighting' => false]);
        $map = $this->createMapDefinition();

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/maps/' . $map->id . '/enter?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_teleport_to_map(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['current_map_id' => 1, 'is_fighting' => false]);
        $map = $this->createMapDefinition();

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/maps/' . $map->id . '/teleport?character_id=' . $character->id);

        $response->assertStatus(200);
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
