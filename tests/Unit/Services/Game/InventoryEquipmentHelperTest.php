<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use App\Services\Game\InventoryEquipmentHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryEquipmentHelperTest extends TestCase
{
    use RefreshDatabase;

    private InventoryEquipmentHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new InventoryEquipmentHelper;
    }

    #[Test]
    public function determine_equipment_slot_returns_slot_from_item_definition(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'type' => 'weapon',
            'name' => 'Steel Sword',
        ]);
        $item = $this->createItem($character, $definition);

        // Act
        $result = $this->helper->determineEquipmentSlot($character, $item);

        // Assert
        $this->assertEquals('weapon', $result);
    }

    #[Test]
    public function determine_equipment_slot_throws_exception_when_item_has_no_definition(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $item = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => 99999, // non-existent definition
            'quality' => 'common',
            'stats' => [],
            'affixes' => [],
            'is_in_storage' => false,
            'is_equipped' => false,
            'quantity' => 1,
            'slot_index' => 0,
            'sockets' => 0,
            'sell_price' => 0,
        ]);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('该物品没有定义，无法装备');
        $this->helper->determineEquipmentSlot($character, $item);
    }

    #[Test]
    public function determine_equipment_slot_throws_exception_when_item_cannot_be_equipped(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'type' => 'potion', // potions cannot be equipped
            'name' => 'Health Potion',
        ]);
        $item = $this->createItem($character, $definition);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('该物品无法装备');
        $this->helper->determineEquipmentSlot($character, $item);
    }

    #[Test]
    public function determine_equipment_slot_returns_ring_slot_for_ring_items(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'type' => 'ring',
            'name' => 'Gold Ring',
        ]);
        $item = $this->createItem($character, $definition);

        // Act
        $result = $this->helper->determineEquipmentSlot($character, $item);

        // Assert
        $this->assertEquals('ring', $result);
    }

    #[Test]
    public function find_available_ring_slot_returns_ring_when_first_ring_slot_is_empty(): void
    {
        // Arrange
        $character = $this->createCharacter();
        // Ensure ring slot exists but is empty
        GameEquipment::create([
            'character_id' => $character->id,
            'slot' => 'ring',
            'item_id' => null,
        ]);

        // Act
        $result = $this->helper->findAvailableRingSlot($character);

        // Assert
        $this->assertEquals('ring', $result);
    }

    #[Test]
    public function find_available_ring_slot_returns_ring_when_first_slot_is_occupied(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $ringDefinition = $this->createItemDefinition(['type' => 'ring']);
        $existingRing = $this->createItem($character, $ringDefinition);

        // Create ring slot with existing ring equipped
        GameEquipment::create([
            'character_id' => $character->id,
            'slot' => 'ring',
            'item_id' => $existingRing->id,
        ]);

        // Act
        $result = $this->helper->findAvailableRingSlot($character);

        // Assert
        $this->assertEquals('ring', $result);
    }

    #[Test]
    public function get_or_create_equipment_slot_returns_existing_slot(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $existingSlot = GameEquipment::create([
            'character_id' => $character->id,
            'slot' => 'helmet',
            'item_id' => null,
        ]);

        // Act
        $result = $this->helper->getOrCreateEquipmentSlot($character, 'helmet');

        // Assert
        $this->assertEquals($existingSlot->id, $result->id);
        $this->assertEquals('helmet', $result->slot);
    }

    #[Test]
    public function get_or_create_equipment_slot_creates_new_slot_when_not_exists(): void
    {
        // Arrange
        $character = $this->createCharacter();

        // Act
        $result = $this->helper->getOrCreateEquipmentSlot($character, 'amulet');

        // Assert
        $this->assertEquals('amulet', $result->slot);
        $this->assertEquals($character->id, $result->character_id);
        $this->assertNull($result->item_id);
    }

    #[Test]
    public function handle_unequip_if_needed_returns_old_item_when_equipped(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition(['type' => 'weapon']);
        $oldItem = $this->createItem($character, $definition, ['slot_index' => 1]);
        $equipmentSlot = GameEquipment::create([
            'character_id' => $character->id,
            'slot' => 'weapon',
            'item_id' => $oldItem->id,
        ]);

        // Act
        $result = $this->helper->handleUnequipIfNeeded($character, $equipmentSlot);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($oldItem->id, $result->id);
        $this->assertFalse($result->is_equipped);
    }

    #[Test]
    public function handle_unequip_if_needed_returns_null_when_slot_empty(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $equipmentSlot = GameEquipment::create([
            'character_id' => $character->id,
            'slot' => 'weapon',
            'item_id' => null,
        ]);

        // Act
        $result = $this->helper->handleUnequipIfNeeded($character, $equipmentSlot);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function is_item_equipped_returns_true_when_item_is_equipped(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition(['type' => 'weapon']);
        $equippedItem = $this->createItem($character, $definition, ['is_equipped' => true]);
        GameEquipment::create([
            'character_id' => $character->id,
            'slot' => 'weapon',
            'item_id' => $equippedItem->id,
        ]);

        // Act
        $result = $this->helper->isItemEquipped($character, $equippedItem->id);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function is_item_equipped_returns_false_when_item_not_equipped(): void
    {
        // Arrange
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition(['type' => 'weapon']);
        $item = $this->createItem($character, $definition, ['is_equipped' => false]);

        // Act
        $result = $this->helper->isItemEquipped($character, $item->id);

        // Assert
        $this->assertFalse($result);
    }

    private function createCharacter(array $attributes = []): GameCharacter
    {
        $user = User::factory()->create();
        $character = GameCharacter::create(array_merge([
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
            'skill_points' => 0,
            'stat_points' => 0,
            'difficulty_tier' => 0,
            'is_fighting' => false,
            'current_hp' => 100,
            'current_mana' => 50,
        ], $attributes));

        foreach (config('game.slots', ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring', 'amulet']) as $slot) {
            GameEquipment::create([
                'character_id' => $character->id,
                'slot' => $slot,
                'item_id' => null,
            ]);
        }

        return $character;
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
            'icon' => 'weapon',
            'description' => 'Test definition',
            'is_active' => true,
            'buy_price' => 100,
        ], $attributes));
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
            'slot_index' => 0,
            'sockets' => $definition->sockets ?? 0,
            'sell_price' => 0,
        ], $attributes))->load('definition');
    }
}
