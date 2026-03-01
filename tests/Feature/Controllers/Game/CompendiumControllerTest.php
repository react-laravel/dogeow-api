<?php

namespace Tests\Feature\Controllers\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompendiumControllerTest extends TestCase
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
            'discovered_items' => [],
            'discovered_monsters' => [],
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
            'icon' => 'sword',
            'description' => 'Basic item definition',
            'is_active' => true,
            'buy_price' => 100,
        ], $attributes));
    }

    private function createMonsterDefinition(array $attributes = []): GameMonsterDefinition
    {
        return GameMonsterDefinition::create(array_merge([
            'name' => 'Slime',
            'level' => 1,
            'type' => 'normal',
            'hp' => 50,
            'mana' => 0,
            'attack' => 5,
            'defense' => 0,
            'experience' => 10,
            'copper_drop_min' => 1,
            'copper_drop_max' => 5,
            'attack_speed' => 1.0,
            'move_speed' => 1.0,
            'is_active' => true,
            'drop_table' => [],
        ], $attributes));
    }

    public function test_can_get_item_compendium(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $this->createItemDefinition();

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/compendium/items?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_get_monster_compendium(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $this->createMonsterDefinition();

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/compendium/monsters?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_get_monster_drops(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $monster = $this->createMonsterDefinition();

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/compendium/monsters/' . $monster->id . '/drops?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/compendium/items');

        $response->assertStatus(401);
    }
}
