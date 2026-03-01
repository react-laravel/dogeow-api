<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use App\Services\Game\GameInventoryService;
use App\Services\Game\GameShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GameShopServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GameShopService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GameShopService;
        Cache::flush();
        config([
            'game.shop.equipment_count_min' => 1,
            'game.shop.equipment_count_max' => 1,
        ]);
    }

    public function test_get_shop_items_returns_unique_potions_and_cached_equipment(): void
    {
        $character = $this->createCharacter(['level' => 12, 'copper' => 345]);
        $this->createItemDefinition([
            'name' => '初级生命药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'required_level' => 1,
            'buy_price' => 25,
        ]);
        $higherPotion = $this->createItemDefinition([
            'name' => '高级生命药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'required_level' => 10,
            'buy_price' => 40,
        ]);
        $manaPotion = $this->createItemDefinition([
            'name' => '法力药水',
            'type' => 'potion',
            'sub_type' => 'mp',
            'required_level' => 5,
            'buy_price' => 30,
        ]);
        $equipment = $this->createItemDefinition([
            'name' => '商店长剑',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'required_level' => 5,
            'buy_price' => 120,
        ]);

        $result = $this->service->getShopItems($character);

        $this->assertCount(3, $result['items']);
        $this->assertSame(345, $result['player_copper']);
        $this->assertGreaterThan(time(), $result['next_refresh_at']);
        $this->assertSame([], $result['purchased']);
        $this->assertSame(
            [$higherPotion->id, $manaPotion->id, $equipment->id],
            $result['items']->pluck('id')->all()
        );
        $this->assertSame(
            [40, 30, 120],
            $result['items']->pluck('buy_price')->all()
        );
        $this->assertSame(
            [12, 9],
            $result['items']->take(2)->pluck('sell_price')->all()
        );
        $this->assertArrayNotHasKey('sell_price', $result['items']->last());
    }

    public function test_get_shop_items_uses_cache_and_filters_purchased_equipment(): void
    {
        $character = $this->createCharacter(['level' => 10]);
        $potion = $this->createItemDefinition([
            'name' => '生命药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'buy_price' => 20,
        ]);
        $equipment = $this->createItemDefinition([
            'name' => '缓存戒指',
            'type' => 'ring',
            'sub_type' => null,
            'required_level' => 3,
            'buy_price' => 99,
        ]);

        $first = $this->service->getShopItems($character);
        $this->assertSame([$potion->id, $equipment->id], $first['items']->pluck('id')->all());

        $this->service->recordPurchasedItem($character, $equipment->id);

        $second = $this->service->getShopItems($character);

        $this->assertSame([$potion->id], $second['items']->pluck('id')->all());
        $this->assertSame([$equipment->id], $second['purchased']);
    }

    public function test_refresh_shop_clears_cached_equipment_and_deducts_copper(): void
    {
        $character = $this->createCharacter(['level' => 10, 'copper' => 500]);
        $potion = $this->createItemDefinition([
            'name' => '测试药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'buy_price' => 20,
        ]);
        $realEquipment = $this->createItemDefinition([
            'name' => '真实武器',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'required_level' => 2,
            'buy_price' => 88,
        ]);

        $this->service->getShopItems($character);
        Cache::put(sprintf('game_shop_%s', $character->id), [
            'equipment' => [[
                'id' => 999999,
                'name' => 'Stale Cache',
                'type' => 'weapon',
                'sub_type' => 'sword',
                'base_stats' => ['attack' => 1],
                'quality' => 'common',
                'required_level' => 1,
                'icon' => 'weapon',
                'description' => 'stale',
                'buy_price' => 1,
            ]],
            'refreshed_at' => time(),
        ], 1800);

        $result = $this->service->refreshShop($character);

        $this->assertSame(400, $character->fresh()->copper);
        $this->assertSame([$potion->id, $realEquipment->id], $result['items']->pluck('id')->all());
        $this->assertFalse($result['items']->pluck('id')->contains(999999));
    }

    public function test_refresh_shop_requires_enough_copper(): void
    {
        $character = $this->createCharacter(['copper' => 99]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('货币不足，强制刷新需要 1 银币');
        $this->service->refreshShop($character);
    }

    public function test_buy_item_stacks_existing_potions_and_deducts_copper(): void
    {
        $character = $this->createCharacter(['copper' => 500]);
        $potionDefinition = $this->createItemDefinition([
            'name' => '小型生命药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'buy_price' => 30,
        ]);
        $existing = $this->createItem($character, $potionDefinition, [
            'quantity' => 2,
            'slot_index' => 0,
            'sell_price' => 6,
        ]);

        $result = $this->service->buyItem($character, $potionDefinition->id, 3);

        $this->assertSame(410, $result['copper']);
        $this->assertSame(90, $result['total_price']);
        $this->assertSame(3, $result['quantity']);
        $this->assertSame('小型生命药水', $result['item_name']);
        $this->assertSame(5, $existing->fresh()->quantity);
        $this->assertSame(1, GameItem::where('character_id', $character->id)->count());
    }

    public function test_buy_item_creates_equipment_records_purchased_and_uses_empty_slots(): void
    {
        $character = $this->createCharacter(['copper' => 500, 'level' => 10]);
        $definition = $this->createItemDefinition([
            'name' => '商店护手',
            'type' => 'gloves',
            'sub_type' => null,
            'required_level' => 3,
            'buy_price' => 50,
        ]);
        $this->createItem($character, $definition, [
            'slot_index' => 0,
            'sell_price' => 10,
        ]);
        $this->service->getShopItems($character);

        $result = $this->service->buyItem($character, $definition->id, 2);

        $newItems = GameItem::where('character_id', $character->id)
            ->where('definition_id', $definition->id)
            ->where('slot_index', '!=', 0)
            ->orderBy('slot_index')
            ->get();

        $this->assertSame(400, $result['copper']);
        $this->assertSame(100, $result['total_price']);
        $this->assertCount(2, $newItems);
        $this->assertSame([1, 2], $newItems->pluck('slot_index')->all());
        $this->assertSame([$definition->id], $this->service->getShopItems($character)['purchased']);
    }

    public function test_buy_item_rejects_invalid_level_copper_and_inventory_capacity_cases(): void
    {
        $character = $this->createCharacter(['copper' => 20, 'level' => 2]);
        $inactive = $this->createItemDefinition([
            'is_active' => false,
            'buy_price' => 10,
        ]);
        $highLevel = $this->createItemDefinition([
            'required_level' => 5,
            'buy_price' => 10,
        ]);
        $expensive = $this->createItemDefinition([
            'buy_price' => 100,
        ]);

        try {
            $this->service->buyItem($character, $inactive->id);
            $this->fail('Expected inactive item exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('物品不存在或不可购买', $e->getMessage());
        }

        try {
            $this->service->buyItem($character, $highLevel->id);
            $this->fail('Expected level requirement exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('需要等级 5', $e->getMessage());
        }

        try {
            $this->service->buyItem($character, $expensive->id);
            $this->fail('Expected currency exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('货币不足', $e->getMessage());
        }

        $fullPotionCharacter = $this->createCharacter(['copper' => 1000, 'level' => 10]);
        $potionDefinition = $this->createItemDefinition([
            'name' => '背包药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'buy_price' => 10,
        ]);
        $filler = $this->createItemDefinition([
            'name' => 'Filler Sword',
            'buy_price' => 1,
        ]);
        $this->fillInventory($fullPotionCharacter, $filler, GameInventoryService::INVENTORY_SIZE);

        try {
            $this->service->buyItem($fullPotionCharacter, $potionDefinition->id);
            $this->fail('Expected full inventory potion exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('背包已满', $e->getMessage());
        }

        $fullEquipmentCharacter = $this->createCharacter(['copper' => 1000, 'level' => 10]);
        $equipmentDefinition = $this->createItemDefinition([
            'name' => '背包装备',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'buy_price' => 10,
        ]);
        $this->fillInventory($fullEquipmentCharacter, $filler, GameInventoryService::INVENTORY_SIZE - 1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('背包空间不足');
        $this->service->buyItem($fullEquipmentCharacter, $equipmentDefinition->id, 2);
    }

    public function test_sell_item_updates_stack_and_copper_or_deletes_item(): void
    {
        $character = $this->createCharacter(['copper' => 100]);
        $definition = $this->createItemDefinition([
            'name' => '出售长剑',
            'buy_price' => 100,
        ]);
        $stackItem = $this->createItem($character, $definition, [
            'quantity' => 3,
            'slot_index' => 0,
        ]);
        $singleItem = $this->createItem($character, $definition, [
            'quantity' => 1,
            'slot_index' => 1,
        ]);

        $first = $this->service->sellItem($character, $stackItem->id, 2);
        $second = $this->service->sellItem($character->fresh(), $singleItem->id, 1);

        $this->assertSame(160, $first['copper']);
        $this->assertSame(60, $first['sell_price']);
        $this->assertSame(1, $stackItem->fresh()->quantity);
        $this->assertSame(190, $second['copper']);
        $this->assertNull(GameItem::find($singleItem->id));
    }

    public function test_sell_item_rejects_missing_storage_equipped_and_insufficient_quantity_cases(): void
    {
        $character = $this->createCharacter();
        $definition = $this->createItemDefinition([
            'name' => '限制短剑',
            'buy_price' => 50,
        ]);
        $storageItem = $this->createItem($character, $definition, [
            'is_in_storage' => true,
            'slot_index' => 0,
        ]);
        $equippedItem = $this->createItem($character, $definition, [
            'slot_index' => 1,
        ]);
        $character->equipment()->where('slot', 'weapon')->update(['item_id' => $equippedItem->id]);
        $smallStack = $this->createItem($character, $definition, [
            'quantity' => 1,
            'slot_index' => 2,
        ]);

        try {
            $this->service->sellItem($character, 999999);
            $this->fail('Expected missing item exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('物品不存在或不属于你', $e->getMessage());
        }

        try {
            $this->service->sellItem($character, $storageItem->id);
            $this->fail('Expected storage restriction exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('请先将物品从仓库移到背包', $e->getMessage());
        }

        try {
            $this->service->sellItem($character, $equippedItem->id);
            $this->fail('Expected equipped restriction exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('请先卸下装备', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('物品数量不足');
        $this->service->sellItem($character, $smallStack->id, 2);
    }

    private function createCharacter(array $attributes = []): GameCharacter
    {
        $user = User::factory()->create();
        $character = GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'ShopHero' . $user->id,
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 10,
            'experience' => 0,
            'copper' => 300,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
            'difficulty_tier' => 0,
            'is_fighting' => false,
            'current_hp' => 50,
            'current_mana' => 20,
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
            'name' => '商店测试物品',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'sockets' => 0,
            'gem_stats' => null,
            'base_stats' => ['attack' => 10],
            'required_level' => 1,
            'icon' => 'weapon',
            'description' => 'Shop test definition',
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
            'sockets' => 0,
            'sell_price' => 30,
        ], $attributes))->load('definition');
    }

    private function fillInventory(GameCharacter $character, GameItemDefinition $definition, int $count): void
    {
        for ($slot = 0; $slot < $count; $slot++) {
            $this->createItem($character, $definition, [
                'slot_index' => $slot,
                'sell_price' => 1,
            ]);
        }
    }
}
