<?php

namespace Tests\Feature\Controllers\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GemControllerTest extends TestCase
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
            'sockets' => 1,
            'gem_stats' => null,
            'base_stats' => ['attack' => 10],
            'required_level' => 1,
            'icon' => 'sword',
            'description' => 'Basic item definition',
            'is_active' => true,
            'buy_price' => 100,
        ], $attributes));
    }

    private function createGemDefinition(array $attributes = []): GameItemDefinition
    {
        return GameItemDefinition::create(array_merge([
            'name' => 'Ruby',
            'type' => 'gem',
            'sub_type' => 'gem',
            'sockets' => 0,
            'gem_stats' => ['attack' => 5],
            'base_stats' => [],
            'required_level' => 1,
            'icon' => 'ruby',
            'description' => 'A red gem',
            'is_active' => true,
            'buy_price' => 50,
        ], $attributes));
    }

    public function test_can_get_gems_for_item(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $itemDef = $this->createItemDefinition(['sockets' => 2]);
        $item = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $itemDef->id,
            'quality' => 'common',
            'stats' => $itemDef->base_stats,
            'affixes' => [],
            'is_in_storage' => false,
            'is_equipped' => false,
            'quantity' => 1,
            'slot_index' => 0,
            'sockets' => 2,
            'sell_price' => 0,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/gems?character_id=' . $character->id . '&item_id=' . $item->id);

        $response->assertStatus(200);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/gems');

        $response->assertStatus(401);
    }
}
