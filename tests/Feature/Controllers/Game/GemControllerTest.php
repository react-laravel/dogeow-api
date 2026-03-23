<?php

namespace Tests\Feature\Controllers\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameItemGem;
use App\Models\User;
use App\Services\Game\GameInventoryService;
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
            'sub_type' => null,
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

    public function test_can_socket_gem_into_equipment(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $equipmentDef = $this->createItemDefinition(['sockets' => 1]);
        $gemDef = $this->createGemDefinition();
        $equipment = $this->createItem($character, $equipmentDef, ['sockets' => 1]);
        $gemItem = $this->createItem($character, $gemDef);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/gems/socket?character_id=' . $character->id, [
                'item_id' => $equipment->id,
                'gem_item_id' => $gemItem->id,
                'socket_index' => 0,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', '宝石镶嵌成功');

        $this->assertDatabaseHas('game_item_gems', [
            'item_id' => $equipment->id,
            'gem_definition_id' => $gemDef->id,
            'socket_index' => 0,
        ]);
        $this->assertDatabaseMissing('game_items', ['id' => $gemItem->id]);
    }

    public function test_socket_rejects_non_gem_items(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $equipment = $this->createItem($character, $this->createItemDefinition(['sockets' => 1]), ['sockets' => 1]);
        $notGem = $this->createItem($character, $this->createItemDefinition([
            'name' => 'Potion',
            'type' => 'potion',
            'sub_type' => 'hp',
            'sockets' => 0,
            'base_stats' => [],
        ]));

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/gems/socket?character_id=' . $character->id, [
                'item_id' => $equipment->id,
                'gem_item_id' => $notGem->id,
                'socket_index' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', '只能镶嵌宝石');
    }

    public function test_socket_rejects_invalid_socket_conditions(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $gemDef = $this->createGemDefinition();
        $gemItem = $this->createItem($character, $gemDef);

        $nonEquipment = $this->createItem($character, $this->createItemDefinition([
            'name' => 'Potion',
            'type' => 'potion',
            'sub_type' => 'hp',
            'sockets' => 0,
            'base_stats' => [],
        ]));
        $noSocketEquipment = $this->createItem($character, $this->createItemDefinition(['sockets' => 0]), ['sockets' => 0]);
        $socketedEquipment = $this->createItem($character, $this->createItemDefinition(['sockets' => 1]), ['sockets' => 1]);
        GameItemGem::create([
            'item_id' => $socketedEquipment->id,
            'gem_definition_id' => $gemDef->id,
            'socket_index' => 0,
        ]);

        $this->actingAs($user)
            ->postJson('/api/rpg/gems/socket?character_id=' . $character->id, [
                'item_id' => $nonEquipment->id,
                'gem_item_id' => $gemItem->id,
                'socket_index' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '只能向装备镶嵌宝石');

        $this->actingAs($user)
            ->postJson('/api/rpg/gems/socket?character_id=' . $character->id, [
                'item_id' => $noSocketEquipment->id,
                'gem_item_id' => $gemItem->id,
                'socket_index' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '该装备没有宝石插槽');

        $this->actingAs($user)
            ->postJson('/api/rpg/gems/socket?character_id=' . $character->id, [
                'item_id' => $socketedEquipment->id,
                'gem_item_id' => $gemItem->id,
                'socket_index' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '插槽索引超出范围');

        $this->actingAs($user)
            ->postJson('/api/rpg/gems/socket?character_id=' . $character->id, [
                'item_id' => $socketedEquipment->id,
                'gem_item_id' => $gemItem->id,
                'socket_index' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '该插槽已有宝石，请先卸下');
    }

    public function test_can_unsocket_gem_from_common_equipment(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $equipment = $this->createItem($character, $this->createItemDefinition(['sockets' => 1]), [
            'quality' => 'common',
            'sockets' => 1,
        ]);
        $gemDef = $this->createGemDefinition();
        $socketedGem = GameItemGem::create([
            'item_id' => $equipment->id,
            'gem_definition_id' => $gemDef->id,
            'socket_index' => 0,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/gems/unsocket?character_id=' . $character->id, [
                'item_id' => $equipment->id,
                'socket_index' => 0,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', '宝石卸下成功');

        $this->assertDatabaseMissing('game_item_gems', ['id' => $socketedGem->id]);
        $this->assertDatabaseHas('game_items', [
            'character_id' => $character->id,
            'definition_id' => $gemDef->id,
            'is_in_storage' => 0,
            'quantity' => 1,
        ]);
    }

    public function test_unsocket_rejects_invalid_conditions(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $gemDef = $this->createGemDefinition();
        $rareEquipment = $this->createItem($character, $this->createItemDefinition(['sockets' => 1]), [
            'quality' => 'rare',
            'sockets' => 1,
        ]);
        $commonEquipment = $this->createItem($character, $this->createItemDefinition(['sockets' => 1]), [
            'quality' => 'common',
            'sockets' => 1,
        ]);
        $fullInventoryEquipment = $this->createItem($character, $this->createItemDefinition(['sockets' => 1]), [
            'quality' => 'common',
            'sockets' => 1,
        ]);
        GameItemGem::create([
            'item_id' => $fullInventoryEquipment->id,
            'gem_definition_id' => $gemDef->id,
            'socket_index' => 0,
        ]);

        for ($i = 0; $i < GameInventoryService::INVENTORY_SIZE; $i++) {
            $this->createItem($character, $this->createItemDefinition([
                'name' => 'Fill ' . $i,
                'sub_type' => 'sword',
            ]), [
                'slot_index' => $i,
            ]);
        }

        $this->actingAs($user)
            ->postJson('/api/rpg/gems/unsocket?character_id=' . $character->id, [
                'item_id' => $rareEquipment->id,
                'socket_index' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '该装备无法取下宝石');

        $this->actingAs($user)
            ->postJson('/api/rpg/gems/unsocket?character_id=' . $character->id, [
                'item_id' => $commonEquipment->id,
                'socket_index' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '该插槽没有宝石');

        $this->actingAs($user)
            ->postJson('/api/rpg/gems/unsocket?character_id=' . $character->id, [
                'item_id' => $fullInventoryEquipment->id,
                'socket_index' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '背包已满，无法卸下宝石');
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/gems');

        $response->assertStatus(401);
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
            'slot_index' => null,
            'sockets' => $definition->sockets ?? 0,
            'sell_price' => 0,
        ], $attributes));
    }
}
