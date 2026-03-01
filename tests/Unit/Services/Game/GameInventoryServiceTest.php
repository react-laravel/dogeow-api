<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use App\Services\Game\GameInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GameInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GameInventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GameInventoryService;
        Cache::flush();
    }

    public function test_get_inventory_separates_inventory_storage_and_equipment_and_calculates_sell_prices(): void
    {
        $character = $this->createCharacter();
        $weaponDefinition = $this->createItemDefinition([
            'name' => 'Pack Sword',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'base_stats' => ['attack' => 12],
        ]);
        $ringDefinition = $this->createItemDefinition([
            'name' => 'Vault Ring',
            'type' => 'ring',
            'sub_type' => null,
            'base_stats' => ['crit_rate' => 0.01],
        ]);

        $inventoryItem = $this->createItem($character, $weaponDefinition, [
            'slot_index' => 2,
            'sell_price' => 0,
        ]);
        $storageItem = $this->createItem($character, $ringDefinition, [
            'is_in_storage' => true,
            'slot_index' => 4,
            'sell_price' => 0,
        ]);
        $equippedItem = $this->createEquippedItem($character, $weaponDefinition, 'weapon', [
            'sell_price' => 0,
        ]);

        $result = $this->service->getInventory($character);

        $this->assertCount(1, $result['inventory']);
        $this->assertSame($inventoryItem->id, $result['inventory']->first()->id);
        $this->assertCount(1, $result['storage']);
        $this->assertSame($storageItem->id, $result['storage']->first()->id);
        $this->assertArrayHasKey('weapon', $result['equipment']->all());
        $this->assertSame($equippedItem->id, $result['equipment']['weapon']->item->id);
        $this->assertSame(GameInventoryService::INVENTORY_SIZE, $result['inventory_size']);
        $this->assertSame(GameInventoryService::STORAGE_SIZE, $result['storage_size']);
        $this->assertGreaterThan(0, $inventoryItem->fresh()->sell_price);
        $this->assertGreaterThan(0, $storageItem->fresh()->sell_price);
        $this->assertGreaterThan(0, $equippedItem->fresh()->sell_price);
    }

    public function test_get_inventory_for_broadcast_returns_serializable_arrays_with_empty_slots(): void
    {
        $character = $this->createCharacter();
        $weaponDefinition = $this->createItemDefinition();
        $ringDefinition = $this->createItemDefinition([
            'name' => 'Signal Ring',
            'type' => 'ring',
            'sub_type' => null,
            'base_stats' => ['crit_rate' => 0.02],
        ]);

        $inventoryItem = $this->createItem($character, $weaponDefinition, ['slot_index' => 0]);
        $storageItem = $this->createItem($character, $weaponDefinition, [
            'is_in_storage' => true,
            'slot_index' => 1,
        ]);
        $equippedRing = $this->createEquippedItem($character, $ringDefinition, 'ring');

        $result = $this->service->getInventoryForBroadcast($character);

        $this->assertIsArray($result['inventory']);
        $this->assertIsArray($result['storage']);
        $this->assertIsArray($result['equipment']);
        $this->assertSame($inventoryItem->id, $result['inventory'][0]['id']);
        $this->assertSame($storageItem->id, $result['storage'][0]['id']);
        $this->assertSame($equippedRing->id, $result['equipment']['ring']['id']);
        $this->assertNull($result['equipment']['weapon']);
    }

    public function test_equip_item_equips_new_weapon_and_unequips_existing_item(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'base_stats' => ['attack' => 15],
        ]);

        $this->createItem($character, $definition, ['slot_index' => 0]);
        $oldItem = $this->createEquippedItem($character, $definition, 'weapon');
        $newItem = $this->createItem($character, $definition, ['slot_index' => 5]);

        $result = $this->service->equipItem($character, $newItem->id);

        $this->assertSame('weapon', $result['equipped_slot']);
        $this->assertSame($newItem->id, $result['equipped_item']->id);
        $this->assertSame($oldItem->id, $result['unequipped_item']->id);
        $this->assertTrue($newItem->fresh()->is_equipped);
        $this->assertNull($newItem->fresh()->slot_index);
        $this->assertFalse($oldItem->fresh()->is_equipped);
        $this->assertSame(1, $oldItem->fresh()->slot_index);
        $this->assertSame($newItem->id, $character->equipment()->where('slot', 'weapon')->first()->item_id);
        $this->assertArrayHasKey('attack', $result['combat_stats']);
        $this->assertArrayHasKey('attack', $result['stats_breakdown']);
    }

    public function test_equip_item_replaces_existing_ring_when_ring_slot_is_occupied(): void
    {
        $character = $this->createCharacter();
        $ringDefinition = $this->createItemDefinition([
            'name' => 'Twin Ring',
            'type' => 'ring',
            'sub_type' => null,
            'base_stats' => ['crit_rate' => 0.01],
        ]);

        $firstRing = $this->createEquippedItem($character, $ringDefinition, 'ring');
        $secondRing = $this->createItem($character, $ringDefinition, ['slot_index' => 3]);

        $result = $this->service->equipItem($character, $secondRing->id);

        $this->assertSame('ring', $result['equipped_slot']);
        // The second ring should replace the first ring in the slot
        $this->assertSame($secondRing->id, $character->equipment()->where('slot', 'ring')->first()->item_id);
    }

    public function test_equip_item_rejects_items_that_fail_level_requirement(): void
    {
        $character = $this->createCharacter(['level' => 3]);
        $definition = $this->createItemDefinition([
            'required_level' => 10,
        ]);
        $item = $this->createItem($character, $definition);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('需要等级 10');
        $this->service->equipItem($character, $item->id);
    }

    public function test_equip_item_rejects_items_without_definition_or_equipment_slot(): void
    {
        $character = $this->createCharacter();
        $missingDefinitionItem = $this->createItem($character, $this->createItemDefinition());
        $missingDefinitionItem->definition_id = 999999;
        $missingDefinitionItem->save();

        try {
            $this->service->equipItem($character, $missingDefinitionItem->id);
            $this->fail('Expected missing definition exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('该物品没有定义，无法装备', $e->getMessage());
        }

        $potionDefinition = $this->createItemDefinition([
            'name' => 'Loose Potion',
            'type' => 'potion',
            'sub_type' => 'hp',
            'base_stats' => ['max_hp' => 50],
        ]);
        $potion = $this->createItem($character, $potionDefinition, ['slot_index' => 1]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('该物品无法装备');
        $this->service->equipItem($character, $potion->id);
    }

    public function test_unequip_item_moves_item_back_to_first_empty_inventory_slot(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition();

        $this->createItem($character, $definition, ['slot_index' => 0]);
        $equippedItem = $this->createEquippedItem($character, $definition, 'weapon');

        $result = $this->service->unequipItem($character, 'weapon');

        $this->assertSame($equippedItem->id, $result['item']->id);
        $this->assertFalse($equippedItem->fresh()->is_equipped);
        $this->assertSame(1, $equippedItem->fresh()->slot_index);
        $this->assertNull($character->equipment()->where('slot', 'weapon')->first()->item_id);
        $this->assertArrayHasKey('attack', $result['combat_stats']);
    }

    public function test_unequip_item_rejects_empty_slots_and_full_inventory(): void
    {
        $character = $this->createCharacter();

        try {
            $this->service->unequipItem($character, 'weapon');
            $this->fail('Expected empty slot exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('该槽位没有装备', $e->getMessage());
        }

        $definition = $this->createItemDefinition();
        $equippedItem = $this->createEquippedItem($character, $definition, 'weapon');
        $this->fillContainer($character, $definition, false, GameInventoryService::INVENTORY_SIZE);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('背包已满');
        $this->service->unequipItem($character->fresh(), 'weapon');

        $this->assertTrue($equippedItem->fresh()->is_equipped);
    }

    public function test_sell_item_reduces_stack_and_adds_copper(): void
    {
        $character = $this->createCharacter(['copper' => 20]);
        $definition = $this->createItemDefinition([
            'base_stats' => ['attack' => 10],
        ]);
        $item = $this->createItem($character, $definition, [
            'quantity' => 3,
            'slot_index' => 0,
        ]);
        $expectedSellPrice = $item->calculateSellPrice() * 2;

        $result = $this->service->sellItem($character, $item->id, 2);

        $this->assertSame(20 + $expectedSellPrice, $result['copper']);
        $this->assertSame($expectedSellPrice, $result['sell_price']);
        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_sell_item_deletes_entire_stack_and_rejects_invalid_quantities_or_equipped_items(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition();
        $stackItem = $this->createItem($character, $definition, ['quantity' => 1]);

        $this->service->sellItem($character, $stackItem->id);
        $this->assertNull(GameItem::find($stackItem->id));

        $insufficientItem = $this->createItem($character, $definition, ['quantity' => 1, 'slot_index' => 2]);
        try {
            $this->service->sellItem($character, $insufficientItem->id, 2);
            $this->fail('Expected insufficient quantity exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('物品数量不足', $e->getMessage());
        }

        $equippedLikeItem = $this->createItem($character, $definition, [
            'is_equipped' => false,
            'slot_index' => 3,
        ]);
        $character->equipment()->where('slot', 'weapon')->update(['item_id' => $equippedLikeItem->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('请先卸下装备');
        $this->service->sellItem($character, $equippedLikeItem->id);
    }

    public function test_move_item_moves_between_inventory_and_storage_with_requested_slot(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition();
        $item = $this->createItem($character, $definition, ['slot_index' => 6]);

        $toStorage = $this->service->moveItem($character, $item->id, true, 8);
        $this->assertTrue($toStorage['item']->fresh()->is_in_storage);
        $this->assertSame(8, $toStorage['item']->fresh()->slot_index);

        $toInventory = $this->service->moveItem($character, $item->id, false, 2);
        $this->assertFalse($toInventory['item']->fresh()->is_in_storage);
        $this->assertSame(2, $toInventory['item']->fresh()->slot_index);
    }

    public function test_move_item_rejects_when_storage_or_inventory_is_full(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition();
        $inventoryItem = $this->createItem($character, $definition, ['slot_index' => 0]);
        $storageItem = $this->createItem($character, $definition, [
            'is_in_storage' => true,
            'slot_index' => 0,
        ]);

        $this->fillContainer($character, $definition, true, GameInventoryService::STORAGE_SIZE - 1, 1);

        try {
            $this->service->moveItem($character, $inventoryItem->id, true);
            $this->fail('Expected storage full exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('仓库已满', $e->getMessage());
        }

        GameItem::where('id', '!=', $storageItem->id)
            ->where('is_in_storage', false)
            ->delete();
        $this->fillContainer($character, $definition, false, GameInventoryService::INVENTORY_SIZE);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('背包已满');
        $this->service->moveItem($character->fresh(), $storageItem->id, false);
    }

    public function test_use_potion_restores_hp_and_mana_and_reduces_stack(): void
    {
        $character = $this->createCharacter([
            'class' => 'mage',
            'current_hp' => 10,
            'current_mana' => 5,
        ]);
        $potionDefinition = $this->createItemDefinition([
            'name' => '混合药剂',
            'type' => 'potion',
            'sub_type' => 'hp',
            'base_stats' => ['max_hp' => 30, 'max_mana' => 20],
        ]);
        $potion = $this->createItem($character, $potionDefinition, [
            'quantity' => 2,
            'slot_index' => 0,
        ]);

        $result = $this->service->usePotion($character, $potion->id);

        $this->assertSame(40, $result['current_hp']);
        $this->assertSame(25, $result['current_mana']);
        $this->assertStringContainsString('30 点生命值和20 点法力值', $result['message']);
        $this->assertSame(1, $potion->fresh()->quantity);
    }

    public function test_use_potion_deletes_last_item_and_rejects_non_potions(): void
    {
        $character = $this->createCharacter([
            'current_hp' => 1,
            'current_mana' => 1,
        ]);
        $potionDefinition = $this->createItemDefinition([
            'name' => '生命药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'base_stats' => ['restore_amount' => 25],
        ]);
        $lastPotion = $this->createItem($character, $potionDefinition, ['quantity' => 1]);

        $result = $this->service->usePotion($character, $lastPotion->id);

        $this->assertStringContainsString('恢复了 25 点生命值', $result['message']);
        $this->assertNull(GameItem::find($lastPotion->id));

        $weapon = $this->createItem($character, $this->createItemDefinition(), ['slot_index' => 2]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('该物品不是药品');
        $this->service->usePotion($character, $weapon->id);
    }

    public function test_sort_inventory_supports_price_quality_and_default_ordering(): void
    {
        $character = $this->createCharacter();
        $weaponDefinition = $this->createItemDefinition([
            'name' => 'Price Sword',
            'base_stats' => ['attack' => 20],
        ]);
        $ringDefinition = $this->createItemDefinition([
            'name' => 'Cheap Ring',
            'type' => 'ring',
            'sub_type' => null,
            'base_stats' => ['crit_rate' => 0.01],
        ]);
        $amuletDefinition = $this->createItemDefinition([
            'name' => 'Necklace',
            'type' => 'amulet',
            'sub_type' => null,
            'base_stats' => ['max_hp' => 40],
        ]);

        $cheap = $this->createItem($character, $ringDefinition, [
            'quality' => 'common',
            'quantity' => 1,
            'sell_price' => 1,
            'slot_index' => 9,
        ]);
        $expensive = $this->createItem($character, $weaponDefinition, [
            'quality' => 'rare',
            'quantity' => 1,
            'sell_price' => 40,
            'slot_index' => 4,
        ]);
        $middle = $this->createItem($character, $amuletDefinition, [
            'quality' => 'magic',
            'quantity' => 2,
            'sell_price' => 10,
            'slot_index' => 1,
        ]);

        $priceResult = $this->service->sortInventory($character, 'price');
        $this->assertSame(
            [$expensive->id, $middle->id, $cheap->id],
            GameItem::where('character_id', $character->id)->where('is_in_storage', false)->orderBy('slot_index')->pluck('id')->all()
        );
        $this->assertCount(3, $priceResult['inventory']);

        $this->service->sortInventory($character, 'quality');
        $this->assertSame(
            [$expensive->id, $middle->id, $cheap->id],
            GameItem::where('character_id', $character->id)->where('is_in_storage', false)->orderBy('slot_index')->pluck('id')->all()
        );

        $this->service->sortInventory($character);
        $this->assertSame(
            [$weaponDefinition->id, $ringDefinition->id, $amuletDefinition->id],
            GameItem::where('character_id', $character->id)
                ->where('is_in_storage', false)
                ->orderBy('slot_index')
                ->pluck('definition_id')
                ->all()
        );
    }

    public function test_sell_items_by_quality_sells_only_sellable_matching_items(): void
    {
        $character = $this->createCharacter(['copper' => 50]);
        $weaponDefinition = $this->createItemDefinition([
            'base_stats' => ['attack' => 11],
        ]);
        $potionDefinition = $this->createItemDefinition([
            'name' => 'Quality Potion',
            'type' => 'potion',
            'sub_type' => 'hp',
            'base_stats' => ['max_hp' => 20],
        ]);
        $gemDefinition = $this->createItemDefinition([
            'name' => 'Quality Gem',
            'type' => 'gem',
            'sub_type' => null,
            'base_stats' => [],
            'gem_stats' => ['attack' => 2],
        ]);

        $sellable = $this->createItem($character, $weaponDefinition, [
            'quality' => 'common',
            'quantity' => 2,
            'slot_index' => 0,
        ]);
        $equippedLike = $this->createItem($character, $weaponDefinition, [
            'quality' => 'common',
            'slot_index' => 1,
        ]);
        $character->equipment()->where('slot', 'weapon')->update(['item_id' => $equippedLike->id]);
        $this->createItem($character, $potionDefinition, [
            'quality' => 'common',
            'slot_index' => 2,
        ]);
        $this->createItem($character, $gemDefinition, [
            'quality' => 'common',
            'slot_index' => 3,
        ]);
        $rareItem = $this->createItem($character, $weaponDefinition, [
            'quality' => 'rare',
            'slot_index' => 4,
        ]);
        $expectedPrice = $sellable->calculateSellPrice() * $sellable->quantity;

        $result = $this->service->sellItemsByQuality($character, 'common');

        $this->assertSame(1, $result['count']);
        $this->assertSame($expectedPrice, $result['total_price']);
        $this->assertSame(50 + $expectedPrice, $result['copper']);
        $this->assertNull(GameItem::find($sellable->id));
        $this->assertNotNull(GameItem::find($equippedLike->id));
        $this->assertNotNull(GameItem::find($rareItem->id));
    }

    public function test_sell_items_by_quality_returns_zero_when_nothing_matches(): void
    {
        $character = $this->createCharacter(['copper' => 77]);
        $definition = $this->createItemDefinition();
        $this->createItem($character, $definition, ['quality' => 'rare']);

        $result = $this->service->sellItemsByQuality($character, 'common');

        $this->assertSame(0, $result['count']);
        $this->assertSame(0, $result['total_price']);
        $this->assertSame(77, $result['copper']);
    }

    public function test_find_empty_slot_ignores_equipped_items_and_returns_null_when_full(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition();

        $equipped = $this->createItem($character, $definition, [
            'is_equipped' => true,
            'slot_index' => 0,
        ]);
        $character->equipment()->where('slot', 'weapon')->update(['item_id' => $equipped->id]);
        $this->createItem($character, $definition, ['slot_index' => 1]);

        $this->assertSame(0, $this->service->findEmptySlot($character, false));

        GameItem::where('character_id', $character->id)->delete();
        $this->fillContainer($character, $definition, false, GameInventoryService::INVENTORY_SIZE);

        $this->assertNull($this->service->findEmptySlot($character->fresh(), false));
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
            'current_hp' => 50,
            'current_mana' => 30,
            'discovered_items' => [],
            'discovered_monsters' => [],
        ], $attributes));

        foreach (config('game.slots') as $slot) {
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
            'description' => 'Inventory test definition',
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

    private function createEquippedItem(GameCharacter $character, GameItemDefinition $definition, string $slot, array $attributes = []): GameItem
    {
        $item = $this->createItem($character, $definition, array_merge([
            'is_equipped' => true,
            'slot_index' => null,
        ], $attributes));

        $character->equipment()->where('slot', $slot)->update(['item_id' => $item->id]);

        return $item;
    }

    private function fillContainer(
        GameCharacter $character,
        GameItemDefinition $definition,
        bool $inStorage,
        int $count,
        int $startingSlot = 0
    ): void {
        for ($slot = 0; $slot < $count; $slot++) {
            $this->createItem($character, $definition, [
                'is_in_storage' => $inStorage,
                'slot_index' => $startingSlot + $slot,
            ]);
        }
    }
}
