<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use App\Services\Game\GameInventoryService;
use App\Services\Game\GameShopService;
use App\Services\Game\InventoryItemCalculator;
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

        $slots = ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring', 'amulet'];
        foreach ($slots as $slot) {
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

    public function test_clear_shop_cache_removes_cached_data(): void
    {
        $character = $this->createCharacter(['level' => 10]);
        $equipment = $this->createItemDefinition([
            'name' => '缓存装备',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'buy_price' => 100,
        ]);

        // Populate cache
        $this->service->getShopItems($character);
        $cacheKey = sprintf('game_shop_%s', $character->id);
        $this->assertNotNull(Cache::get($cacheKey));

        // Clear cache
        $this->service->clearShopCache($character);

        // Verify cache is cleared
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_get_shop_items_with_no_equipment_available(): void
    {
        $character = $this->createCharacter(['level' => 1]);
        $potion = $this->createItemDefinition([
            'name' => '唯一药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'buy_price' => 20,
        ]);

        $result = $this->service->getShopItems($character);

        $this->assertCount(1, $result['items']);
        $this->assertEquals($potion->id, $result['items']->first()['id']);
    }

    public function test_buy_item_with_single_quantity(): void
    {
        $character = $this->createCharacter(['copper' => 1000]);
        $definition = $this->createItemDefinition([
            'name' => '测试物品',
            'buy_price' => 10,
        ]);

        $result = $this->service->buyItem($character, $definition->id, 1);

        $this->assertArrayHasKey('copper', $result);
        $this->assertArrayHasKey('total_price', $result);
        $this->assertEquals(1, $result['quantity']);
    }

    public function test_sell_item_with_single_quantity(): void
    {
        $character = $this->createCharacter(['copper' => 100]);
        $definition = $this->createItemDefinition([
            'name' => '测试物品',
            'buy_price' => 50,
        ]);
        $item = $this->createItem($character, $definition, [
            'quantity' => 5,
            'slot_index' => 0,
        ]);

        $result = $this->service->sellItem($character, $item->id, 1);

        $this->assertArrayHasKey('copper', $result);
        $this->assertArrayHasKey('sell_price', $result);
        $this->assertEquals(1, $result['quantity']);
    }

    public function test_get_shop_items_with_multiple_same_subtype_potions(): void
    {
        $character = $this->createCharacter(['level' => 10]);
        $lowHpPotion = $this->createItemDefinition([
            'name' => '低级生命药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'required_level' => 1,
            'buy_price' => 10,
        ]);
        $midHpPotion = $this->createItemDefinition([
            'name' => '中级生命药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'required_level' => 5,
            'buy_price' => 25,
        ]);
        $highHpPotion = $this->createItemDefinition([
            'name' => '高级生命药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'required_level' => 10,
            'buy_price' => 40,
        ]);

        $result = $this->service->getShopItems($character);

        // Should only show the highest level HP potion
        $hpPotions = $result['items']->filter(fn ($item) => $item['type'] === 'potion' && $item['sub_type'] === 'hp');
        $this->assertCount(1, $hpPotions);
        $this->assertEquals($highHpPotion->id, $hpPotions->first()['id']);
    }

    public function test_record_purchased_item_adds_to_cache(): void
    {
        $character = $this->createCharacter(['level' => 5]);
        $equipment = $this->createItemDefinition([
            'name' => '购买记录装备',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'buy_price' => 50,
        ]);

        $this->service->getShopItems($character);
        $this->service->recordPurchasedItem($character, $equipment->id);

        $result = $this->service->getShopItems($character);
        $this->assertContains($equipment->id, $result['purchased']);
    }

    public function test_buy_item_with_exact_copper_amount(): void
    {
        $character = $this->createCharacter(['copper' => 100]);
        $definition = $this->createItemDefinition([
            'name' => '精确价格物品',
            'buy_price' => 100,
        ]);

        $result = $this->service->buyItem($character, $definition->id, 1);

        $this->assertEquals(0, $result['copper']);
        $this->assertEquals(100, $result['total_price']);
    }

    public function test_sell_item_calculates_correct_sell_price(): void
    {
        $character = $this->createCharacter(['copper' => 0]);
        $definition = $this->createItemDefinition([
            'name' => '出售价格测试',
            'buy_price' => 200,
        ]);
        $item = $this->createItem($character, $definition, [
            'quantity' => 1,
            'slot_index' => 0,
            'sell_price' => 60, // 30% of buy price
        ]);

        $result = $this->service->sellItem($character, $item->id, 1);

        $this->assertEquals(60, $result['sell_price']);
        $this->assertEquals(60, $result['copper']);
    }

    public function test_refresh_shop_updates_next_refresh_timestamp(): void
    {
        $character = $this->createCharacter(['level' => 5, 'copper' => 200]);
        $equipment = $this->createItemDefinition([
            'name' => '刷新测试装备',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'buy_price' => 50,
        ]);

        $result = $this->service->refreshShop($character);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('next_refresh_at', $result);
        $this->assertGreaterThan(time(), $result['next_refresh_at']);
    }

    public function test_get_shop_items_with_zero_copper_character(): void
    {
        $character = $this->createCharacter(['copper' => 0]);
        $equipment = $this->createItemDefinition([
            'name' => '贵重装备',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'buy_price' => 500,
        ]);

        $result = $this->service->getShopItems($character);

        $this->assertEquals(0, $result['player_copper']);
        $this->assertArrayHasKey('items', $result);
    }

    public function test_buy_item_with_high_level_character(): void
    {
        $character = $this->createCharacter(['level' => 50, 'copper' => 5000]);
        $definition = $this->createItemDefinition([
            'name' => '高级装备',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'required_level' => 40,
            'buy_price' => 1000,
        ]);

        $result = $this->service->buyItem($character, $definition->id, 1);

        $this->assertEquals(4000, $result['copper']);
        $this->assertArrayHasKey('item_name', $result);
        $this->assertEquals('高级装备', $result['item_name']);
    }

    public function test_sell_item_with_multiple_stacks(): void
    {
        $character = $this->createCharacter(['copper' => 50]);
        $definition = $this->createItemDefinition([
            'name' => '堆叠物品',
            'type' => 'potion',
            'sub_type' => 'hp',
            'buy_price' => 40,
        ]);
        $item = $this->createItem($character, $definition, [
            'quantity' => 10,
            'slot_index' => 0,
            'sell_price' => 12, // 10 * 12 = 120 for all
        ]);

        $result = $this->service->sellItem($character, $item->id, 5);

        $this->assertEquals(110, $result['copper']); // 50 + (5 * 12)
        $this->assertEquals(5, $item->fresh()->quantity);
    }

    public function test_generate_random_stats_for_helmet(): void
    {
        $character = $this->createCharacter(['level' => 10]);
        $helmet = $this->createItemDefinition([
            'name' => '测试头盔',
            'type' => 'helmet',
            'required_level' => 5,
            'buy_price' => 100,
        ]);

        $result = $this->service->getShopItems($character);

        $helmetItem = $result['items']->firstWhere('id', $helmet->id);
        $this->assertNotNull($helmetItem);
        $this->assertArrayHasKey('defense', $helmetItem['base_stats']);
        $this->assertArrayHasKey('max_hp', $helmetItem['base_stats']);
    }

    public function test_generate_random_stats_for_armor(): void
    {
        $character = $this->createCharacter(['level' => 15]);
        $armor = $this->createItemDefinition([
            'name' => '测试护甲',
            'type' => 'armor',
            'required_level' => 10,
            'buy_price' => 200,
        ]);

        $result = $this->service->getShopItems($character);

        $armorItem = $result['items']->firstWhere('id', $armor->id);
        $this->assertNotNull($armorItem);
        $this->assertArrayHasKey('defense', $armorItem['base_stats']);
        $this->assertArrayHasKey('max_hp', $armorItem['base_stats']);
    }

    public function test_generate_random_stats_for_boots(): void
    {
        $character = $this->createCharacter(['level' => 8]);
        $boots = $this->createItemDefinition([
            'name' => '测试靴子',
            'type' => 'boots',
            'required_level' => 3,
            'buy_price' => 80,
        ]);

        $result = $this->service->getShopItems($character);

        $bootsItem = $result['items']->firstWhere('id', $boots->id);
        $this->assertNotNull($bootsItem);
        $this->assertArrayHasKey('defense', $bootsItem['base_stats']);
        $this->assertArrayHasKey('max_hp', $bootsItem['base_stats']);
    }

    public function test_generate_random_stats_for_belt(): void
    {
        $character = $this->createCharacter(['level' => 12]);
        $belt = $this->createItemDefinition([
            'name' => '测试腰带',
            'type' => 'belt',
            'required_level' => 8,
            'buy_price' => 150,
        ]);

        $result = $this->service->getShopItems($character);

        $beltItem = $result['items']->firstWhere('id', $belt->id);
        $this->assertNotNull($beltItem);
        $this->assertArrayHasKey('max_hp', $beltItem['base_stats']);
        $this->assertArrayHasKey('max_mana', $beltItem['base_stats']);
    }

    public function test_get_shop_items_clears_expired_purchased_cache(): void
    {
        $character = $this->createCharacter(['level' => 10]);
        $equipment = $this->createItemDefinition([
            'name' => '过期缓存装备',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'buy_price' => 100,
        ]);

        Cache::put('game_shop_' . $character->id, [
            'equipment' => [[
                'id' => $equipment->id,
                'name' => $equipment->name,
                'type' => $equipment->type,
                'sub_type' => $equipment->sub_type,
                'base_stats' => ['attack' => 10],
                'quality' => 'common',
                'required_level' => $equipment->required_level,
                'icon' => $equipment->icon,
                'description' => $equipment->description,
                'buy_price' => $equipment->buy_price,
            ]],
            'refreshed_at' => time() - 3600,
        ], 1800);
        Cache::put('game_shop_purchased_' . $character->id, [$equipment->id], 1800);

        $result = $this->service->getShopItems($character);

        $this->assertSame([], $result['purchased']);
        $this->assertNull(Cache::get('game_shop_purchased_' . $character->id));
    }

    public function test_generate_random_quality_can_hit_all_quality_branches(): void
    {
        $calculator = new InventoryItemCalculator;

        config([
            'game.shop.quality_chance' => [
                'mythic' => ['base' => 100, 'per_level' => 0, 'max' => 100],
                'legendary' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'rare' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'magic' => ['base' => 0, 'per_level' => 0, 'max' => 0],
            ],
        ]);
        $this->assertSame('mythic', $calculator->generateRandomQuality(10));

        config([
            'game.shop.quality_chance' => [
                'mythic' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'legendary' => ['base' => 100, 'per_level' => 0, 'max' => 100],
                'rare' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'magic' => ['base' => 0, 'per_level' => 0, 'max' => 0],
            ],
        ]);
        $this->assertSame('legendary', $calculator->generateRandomQuality(10));

        config([
            'game.shop.quality_chance' => [
                'mythic' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'legendary' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'rare' => ['base' => 100, 'per_level' => 0, 'max' => 100],
                'magic' => ['base' => 0, 'per_level' => 0, 'max' => 0],
            ],
        ]);
        $this->assertSame('rare', $calculator->generateRandomQuality(10));

        config([
            'game.shop.quality_chance' => [
                'mythic' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'legendary' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'rare' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'magic' => ['base' => 100, 'per_level' => 0, 'max' => 100],
            ],
        ]);
        $this->assertSame('magic', $calculator->generateRandomQuality(10));

        config([
            'game.shop.quality_chance' => [
                'mythic' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'legendary' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'rare' => ['base' => 0, 'per_level' => 0, 'max' => 0],
                'magic' => ['base' => 0, 'per_level' => 0, 'max' => 0],
            ],
        ]);
        $this->assertSame('common', $calculator->generateRandomQuality(10));
    }

    public function test_generate_random_stats_for_ring_amulet_and_potion(): void
    {
        $calculator = new InventoryItemCalculator;

        $ring = $this->createItemDefinition([
            'name' => '测试戒指',
            'type' => 'ring',
            'required_level' => 8,
        ]);
        $ringStats = $calculator->generateRandomStats($ring);
        $this->assertNotEmpty($ringStats);

        $amulet = $this->createItemDefinition([
            'name' => '测试项链',
            'type' => 'amulet',
            'required_level' => 8,
        ]);
        $amuletStats = $calculator->generateRandomStats($amulet);
        $this->assertArrayHasKey('max_hp', $amuletStats);
        $this->assertArrayHasKey('max_mana', $amuletStats);

        $potion = $this->createItemDefinition([
            'name' => '测试药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'required_level' => 5,
        ]);
        $potionStats = $calculator->generateRandomStats($potion);
        $this->assertArrayHasKey('restore', $potionStats);
        $this->assertTrue(isset($potionStats['max_hp']) || isset($potionStats['max_mana']));
    }

    public function test_calculate_buy_price_uses_base_stats_price_and_config_fallbacks(): void
    {
        $calculator = new InventoryItemCalculator;

        $baseStatsPriceItem = $this->createItemDefinition([
            'name' => '基础价物品',
            'buy_price' => 0,
            'base_stats' => ['price' => 345],
            'required_level' => 10,
            'type' => 'weapon',
        ]);

        $priceFromBaseStats = $calculator->calculateBuyPrice($baseStatsPriceItem, ['attack' => 10], 'common');
        $this->assertSame(345, $priceFromBaseStats);

        config([
            'game.shop.level_price_multiplier' => 'invalid',
            'game.shop.quality_price_multiplier' => 'invalid',
            'game.shop.type_base_price' => 'invalid',
            'game.shop.stat_price' => 'invalid',
        ]);

        $configFallbackItem = $this->createItemDefinition([
            'name' => '配置回退物品',
            'buy_price' => 0,
            'base_stats' => ['attack' => 1],
            'required_level' => 10,
            'type' => 'weapon',
        ]);

        $priceWithFallbacks = $calculator->calculateBuyPrice($configFallbackItem, ['attack' => 10], 'mythic');
        $this->assertSame(24000, $priceWithFallbacks);
    }

    public function test_get_shop_items_returns_empty_purchased_when_shop_cache_missing_refreshed_at(): void
    {
        $character = $this->createCharacter(['level' => 10]);
        $equipment = $this->createItemDefinition([
            'name' => '缓存异常装备',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'required_level' => 5,
            'buy_price' => 120,
        ]);

        Cache::put('game_shop_purchased_' . $character->id, [$equipment->id], 1800);
        Cache::put('game_shop_' . $character->id, [
            'equipment' => [[
                'id' => $equipment->id,
                'name' => $equipment->name,
                'type' => $equipment->type,
                'sub_type' => $equipment->sub_type,
                'base_stats' => ['attack' => 10],
                'quality' => 'common',
                'required_level' => $equipment->required_level,
                'icon' => $equipment->icon,
                'description' => $equipment->description,
                'buy_price' => $equipment->buy_price,
            ]],
        ], 1800);

        $result = $this->service->getShopItems($character);

        $this->assertSame([], $result['purchased']);
    }

    public function test_buy_item_creates_new_potion_stack_when_no_existing_item(): void
    {
        $character = $this->createCharacter(['copper' => 500]);
        $potion = $this->createItemDefinition([
            'name' => '新建药水',
            'type' => 'potion',
            'sub_type' => 'hp',
            'buy_price' => 40,
            'required_level' => 1,
        ]);

        $result = $this->service->buyItem($character, $potion->id, 2);

        $created = GameItem::where('character_id', $character->id)
            ->where('definition_id', $potion->id)
            ->first();

        $this->assertNotNull($created);
        $this->assertSame(2, $created->quantity);
        $this->assertGreaterThan(0, (int) $created->sell_price);
        $this->assertSame(420, $result['copper']);
    }

    public function test_generate_random_stats_covers_rare_random_branches(): void
    {
        $calculator = new InventoryItemCalculator;

        $helmet = $this->createItemDefinition([
            'name' => '随机头盔',
            'type' => 'helmet',
            'required_level' => 10,
        ]);
        $ring = $this->createItemDefinition([
            'name' => '随机戒指',
            'type' => 'ring',
            'required_level' => 10,
        ]);
        $boots = $this->createItemDefinition([
            'name' => '随机靴子',
            'type' => 'boots',
            'required_level' => 10,
        ]);
        $amulet = $this->createItemDefinition([
            'name' => '随机项链',
            'type' => 'amulet',
            'required_level' => 10,
        ]);

        $helmetCrit = false;
        $ringFirstCrit = false;
        $ringSecondCrit = false;
        $ringSecondNonCrit = false;
        $bootsDexterity = false;
        $amuletDefense = false;

        for ($i = 0; $i < 500; $i++) {
            $helmetStats = $calculator->generateRandomStats($helmet);
            if (isset($helmetStats['crit_rate'])) {
                $helmetCrit = true;
            }

            $ringStats = $calculator->generateRandomStats($ring);
            if ((array_key_first($ringStats) === 'crit_rate')) {
                $ringFirstCrit = true;
            }
            if (count($ringStats) >= 2) {
                $keys = array_keys($ringStats);
                $secondKey = $keys[1] ?? null;
                if ($secondKey === 'crit_rate') {
                    $ringSecondCrit = true;
                }
                if ($secondKey !== null && $secondKey !== 'crit_rate') {
                    $ringSecondNonCrit = true;
                }
            }

            $bootsStats = $calculator->generateRandomStats($boots);
            if (isset($bootsStats['dexterity'])) {
                $bootsDexterity = true;
            }

            $amuletStats = $calculator->generateRandomStats($amulet);
            if (isset($amuletStats['defense'])) {
                $amuletDefense = true;
            }

            if ($helmetCrit && $ringFirstCrit && $ringSecondCrit && $ringSecondNonCrit && $bootsDexterity && $amuletDefense) {
                break;
            }
        }

        $this->assertTrue($helmetCrit);
        $this->assertTrue($ringFirstCrit);
        $this->assertTrue($ringSecondCrit);
        $this->assertTrue($ringSecondNonCrit);
        $this->assertTrue($bootsDexterity);
        $this->assertTrue($amuletDefense);
    }

    public function test_calculate_buy_price_uses_type_base_price_when_config_is_array(): void
    {
        $calculator = new InventoryItemCalculator;

        config([
            'game.shop.level_price_multiplier' => 0,
            'game.shop.quality_price_multiplier' => ['common' => 1],
            'game.shop.type_base_price' => ['ring' => 77],
            'game.shop.stat_price' => [],
        ]);

        $item = $this->createItemDefinition([
            'name' => '类型价格戒指',
            'type' => 'ring',
            'required_level' => 1,
            'buy_price' => 0,
            'base_stats' => [],
        ]);

        $price = $calculator->calculateBuyPrice($item, [], 'common');

        $this->assertSame(7700, $price);
    }
}
