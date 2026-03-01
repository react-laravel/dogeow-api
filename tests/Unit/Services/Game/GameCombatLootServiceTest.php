<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\User;
use App\Services\Game\GameCombatLootService;
use App\Services\Game\GameInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCombatLootServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GameCombatLootService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GameCombatLootService;
    }

    public function test_process_death_loot_ignores_alive_invalid_and_missing_monsters(): void
    {
        $character = $this->createCharacter();

        $result = $this->service->processDeathLoot($character, [
            'monsters_updated' => [
                ['id' => 9999, 'hp' => 0],
                ['id' => 1, 'hp' => 10],
                'bad-payload',
            ],
            'loot' => [],
        ]);

        $this->assertSame([], $result);
        $this->assertSame([], $character->fresh()->discovered_monsters ?? []);
    }

    public function test_process_death_loot_discovers_dead_monster_and_creates_item_and_potion(): void
    {
        $character = $this->createCharacter(['level' => 10]);
        $weaponDefinition = $this->createItemDefinition([
            'name' => 'Loot Sword',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'base_stats' => ['attack' => 12, 'defense' => 4],
            'required_level' => 1,
        ]);
        $monster = $this->createMonsterDefinition([
            'level' => 10,
            'drop_table' => [
                'item_chance' => 1.0,
                'potion_chance' => 1.0,
                'item_types' => ['weapon'],
            ],
        ]);

        $result = $this->service->processDeathLoot($character, [
            'monsters_updated' => [
                ['id' => $monster->id, 'hp' => 0],
            ],
            'loot' => [],
        ]);

        $this->assertArrayHasKey('item', $result);
        $this->assertArrayHasKey('potion', $result);
        $this->assertInstanceOf(GameItem::class, $result['item']);
        $this->assertInstanceOf(GameItem::class, $result['potion']);
        $this->assertSame($weaponDefinition->id, $result['item']->definition_id);
        $this->assertSame('potion', $result['potion']->definition->type);

        $character = $character->fresh();
        $this->assertContains($monster->id, $character->discovered_monsters);
        $this->assertContains($weaponDefinition->id, $character->discovered_items);
        $this->assertContains($result['potion']->definition_id, $character->discovered_items);
        $this->assertDatabaseCount('game_items', 2);
    }

    public function test_process_death_loot_preserves_existing_loot_entries(): void
    {
        $character = $this->createCharacter();
        $monster = $this->createMonsterDefinition([
            'drop_table' => [
                'item_chance' => 1.0,
                'potion_chance' => 1.0,
                'item_types' => ['weapon'],
            ],
        ]);

        $result = $this->service->processDeathLoot($character, [
            'monsters_updated' => [
                ['id' => $monster->id, 'hp' => 0],
            ],
            'loot' => [
                'item' => 'existing-item',
                'potion' => 'existing-potion',
            ],
        ]);

        $this->assertSame('existing-item', $result['item']);
        $this->assertSame('existing-potion', $result['potion']);
        $this->assertContains($monster->id, $character->fresh()->discovered_monsters);
    }

    public function test_distribute_rewards_updates_character_experience_and_copper(): void
    {
        $character = $this->createCharacter([
            'experience' => 0,
            'copper' => 10,
        ]);

        $result = $this->service->distributeRewards($character, [
            'experience_gained' => 150,
            'copper_gained' => 75,
            'loot' => ['item' => 'token'],
        ]);

        $this->assertSame(150, $result['experience_gained']);
        $this->assertSame(75, $result['copper_gained']);
        $this->assertSame('token', $result['loot']['item']);
        $this->assertSame(75, $result['loot']['copper']);
        $this->assertSame(150, $character->fresh()->experience);
        $this->assertSame(85, $character->fresh()->copper);
    }

    public function test_create_item_returns_null_when_definition_does_not_exist(): void
    {
        $character = $this->createCharacter();

        $result = $this->service->createItem($character, [
            'type' => 'weapon',
            'quality' => 'common',
            'level' => 5,
        ]);

        $this->assertNull($result);
    }

    public function test_create_item_returns_null_when_inventory_is_full(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'name' => 'Full Inventory Sword',
            'type' => 'weapon',
            'sub_type' => 'sword',
        ]);
        $this->fillInventory($character, $definition);

        $result = $this->service->createItem($character, [
            'type' => 'weapon',
            'quality' => 'common',
            'level' => 5,
        ]);

        $this->assertNull($result);
    }

    public function test_create_item_creates_rare_equipment_with_affixes_and_sockets(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'name' => 'Rare Sword',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'base_stats' => ['attack' => 20, 'defense' => 8],
            'required_level' => 1,
        ]);

        $item = $this->service->createItem($character, [
            'type' => 'weapon',
            'quality' => 'rare',
            'level' => 5,
        ]);

        $this->assertInstanceOf(GameItem::class, $item);
        $this->assertSame($definition->id, $item->definition_id);
        $this->assertSame('rare', $item->quality);
        $this->assertNotEmpty($item->stats);
        $this->assertGreaterThanOrEqual(2, count($item->affixes ?? []));
        $this->assertGreaterThanOrEqual(1, $item->sockets ?? 0);
        $this->assertSame(0, $item->slot_index);
        $this->assertGreaterThan(0, $item->sell_price);
        $this->assertTrue($character->fresh()->hasDiscoveredItem($definition->id));
    }

    public function test_create_potion_returns_null_for_invalid_config(): void
    {
        $character = $this->createCharacter();

        $this->assertNull($this->service->createPotion($character, [
            'sub_type' => 'rage',
            'level' => 'minor',
        ]));
    }

    public function test_create_potion_increments_existing_stack(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'name' => '轻型生命药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'base_stats' => ['max_hp' => 50],
            'gem_stats' => ['restore' => 50],
            'icon' => 'potion',
        ]);
        $existingPotion = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $definition->base_stats,
            'affixes' => [],
            'is_in_storage' => false,
            'is_equipped' => false,
            'quantity' => 2,
            'slot_index' => 0,
            'sockets' => 0,
            'sell_price' => 1,
        ]);

        $result = $this->service->createPotion($character, [
            'sub_type' => 'hp',
            'level' => 'minor',
        ]);

        $this->assertInstanceOf(GameItem::class, $result);
        $this->assertSame($existingPotion->id, $result->id);
        $this->assertSame(3, $existingPotion->fresh()->quantity);
    }

    public function test_create_potion_returns_null_when_inventory_is_full_and_no_stack_exists(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'name' => 'Inventory Filler',
            'type' => 'weapon',
            'sub_type' => 'sword',
        ]);
        $this->fillInventory($character, $definition);

        $result = $this->service->createPotion($character, [
            'sub_type' => 'hp',
            'level' => 'minor',
        ]);

        $this->assertNull($result);
    }

    public function test_create_potion_creates_definition_and_item_when_missing(): void
    {
        $character = $this->createCharacter();

        $potion = $this->service->createPotion($character, [
            'sub_type' => 'hp',
            'level' => 'minor',
        ]);

        $this->assertInstanceOf(GameItem::class, $potion);
        $this->assertSame('potion', $potion->definition->type);
        $this->assertSame('hp', $potion->definition->sub_type);
        $this->assertSame(['max_hp' => 50], $potion->definition->base_stats);
        $this->assertSame(['restore' => 50], $potion->definition->gem_stats);
        $this->assertSame(0, $potion->slot_index);
        $this->assertGreaterThan(0, $potion->sell_price);
        $this->assertTrue($character->fresh()->hasDiscoveredItem($potion->definition_id));
    }

    public function test_create_gem_returns_null_when_inventory_is_full(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'name' => 'Inventory Filler',
            'type' => 'weapon',
            'sub_type' => 'sword',
        ]);
        $this->fillInventory($character, $definition);

        $result = $this->service->createGem($character, 10);

        $this->assertNull($result);
    }

    public function test_create_gem_creates_definition_and_discovers_item(): void
    {
        $character = $this->createCharacter();

        $gem = $this->service->createGem($character, 10);

        $this->assertInstanceOf(GameItem::class, $gem);
        $this->assertSame('gem', $gem->definition->type);
        $this->assertNotEmpty($gem->definition->gem_stats);
        $this->assertGreaterThanOrEqual(10, $gem->definition->buy_price);
        $this->assertSame(0, $gem->slot_index);
        $this->assertTrue($character->fresh()->hasDiscoveredItem($gem->definition_id));
    }

    private function createCharacter(array $attributes = []): GameCharacter
    {
        $user = User::factory()->create();

        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Hero' . $user->id,
            'class' => 'warrior',
            'level' => 1,
            'experience' => 0,
            'copper' => 0,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
            'is_fighting' => false,
            'difficulty_tier' => 0,
            'discovered_items' => [],
            'discovered_monsters' => [],
        ], $attributes));
    }

    private function createItemDefinition(array $attributes = []): GameItemDefinition
    {
        return GameItemDefinition::create(array_merge([
            'name' => 'Test Weapon',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'base_stats' => ['attack' => 10],
            'required_level' => 1,
            'icon' => 'weapon',
            'description' => 'Test definition',
            'is_active' => true,
            'sockets' => 0,
            'gem_stats' => null,
            'buy_price' => 100,
        ], $attributes));
    }

    private function createMonsterDefinition(array $attributes = []): GameMonsterDefinition
    {
        return GameMonsterDefinition::create(array_merge([
            'name' => 'Test Monster',
            'type' => 'normal',
            'level' => 5,
            'hp_base' => 100,
            'hp_per_level' => 10,
            'attack_base' => 15,
            'attack_per_level' => 2,
            'defense_base' => 6,
            'defense_per_level' => 1,
            'experience_base' => 20,
            'experience_per_level' => 5,
            'drop_table' => [],
            'icon' => 'monster',
            'is_active' => true,
        ], $attributes));
    }

    private function fillInventory(GameCharacter $character, GameItemDefinition $definition): void
    {
        for ($slot = 0; $slot < GameInventoryService::INVENTORY_SIZE; $slot++) {
            GameItem::create([
                'character_id' => $character->id,
                'definition_id' => $definition->id,
                'quality' => 'common',
                'stats' => $definition->base_stats,
                'affixes' => [],
                'is_in_storage' => false,
                'is_equipped' => false,
                'quantity' => 1,
                'slot_index' => $slot,
                'sockets' => 0,
                'sell_price' => 1,
            ]);
        }
    }
}
