<?php

namespace Tests\Feature\Controllers\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Hero' . $user->id,
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
            'icon' => 'sword',
            'description' => 'Basic item definition',
            'is_active' => true,
            'buy_price' => 100,
            'sell_price' => 50,
        ], $attributes));
    }

    public function test_can_get_inventory(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/inventory?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_sort_inventory(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/inventory/sort?character_id=' . $character->id, [
                'sort_by' => 'default',
            ]);

        $response->assertStatus(200);
    }

    public function test_can_sell_by_quality(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['copper' => 0]);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/inventory/sell-by-quality?character_id=' . $character->id, [
                'quality' => 'common',
            ]);

        $response->assertStatus(200);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/inventory');

        $response->assertStatus(401);
    }
}
