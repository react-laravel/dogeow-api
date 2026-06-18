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
use Carbon\Carbon;
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
        GameItemDefinition::query()->where('type', 'gem')->delete();
        config([
            'game.shop.equipment_count_min' => 1,
            'game.shop.equipment_count_max' => 1,
            'game.shop.manual_refresh_enabled' => true,
            'game.shop.items_per_category_min' => 1,
            'game.shop.items_per_category_max' => 5,
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

        $this->assertGreaterThanOrEqual(2, $result['items']->count());
        $this->assertSame(345, $result['player_copper']);
        $this->assertGreaterThan(time(), $result['next_refresh_at']);
        $this->assertSame([], $result['purchased']);
        $this->assertTrue($result['items']->pluck('id')->contains($equipment->id));
        $this->assertTrue(
            $result['items']->pluck('id')->contains($higherPotion->id)
            || $result['items']->pluck('id')->contains($manaPotion->id)
        );
        $equipmentListing = $result['items']->firstWhere('id', $equipment->id);
        $this->assertNotNull($equipmentListing);
        $this->assertGreaterThan(0, $equipmentListing['buy_price']);
        $this->assertArrayNotHasKey('sell_price', $equipmentListing);
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
        $this->assertTrue($first['items']->pluck('id')->contains($potion->id));
        $this->assertTrue($first['items']->pluck('id')->contains($equipment->id));

        $equipmentListing = $first['items']->firstWhere('id', $equipment->id);
        $this->assertNotNull($equipmentListing);
        $listingKey = (string) $equipmentListing['listing_id'];
        $this->service->recordPurchasedItem($character, $listingKey);

        $second = $this->service->getShopItems($character);

        $this->assertFalse($second['items']->pluck('listing_id')->contains($listingKey));
        $this->assertSame([$listingKey], $second['purchased']);
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
        Cache::put($this->shopCacheKey($character), [
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
            'gems' => [],
            'refreshed_at' => time(),
        ], 1800);

        $result = $this->service->refreshShop($character);

        $this->assertSame(400, $character->fresh()->copper);
        $this->assertTrue($result['items']->pluck('id')->contains($potion->id));
        $this->assertTrue($result['items']->pluck('id')->contains($realEquipment->id));
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
            'buy_price' => 0,
            'base_stats' => ['price' => 50],
        ]);
        $this->createItem($character, $definition, [
            'slot_index' => 0,
            'sell_price' => 10,
        ]);
        $listingId = 'gloves:' . $definition->id . ':0';
        $this->seedShopEquipmentCache($character, $definition, [
            'base_stats' => ['price' => 50],
            'buy_price' => 50,
            'listing_id' => $listingId,
        ]);

        $result = $this->service->buyItem($character, $definition->id, 2, null, $listingId);

        $newItems = GameItem::where('character_id', $character->id)
            ->where('definition_id', $definition->id)
            ->where('slot_index', '!=', 0)
            ->orderBy('slot_index')
            ->get();

        $this->assertSame(400, $result['copper']);
        $this->assertSame(100, $result['total_price']);
        $this->assertCount(2, $newItems);
        $this->assertSame([1, 2], $newItems->pluck('slot_index')->all());
        $this->assertSame([$listingId], $this->service->getShopItems($character)['purchased']);
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
            'base_stats' => ['price' => 100],
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
            'buy_price' => 0,
            'base_stats' => ['price' => 10],
        ]);
        $this->fillInventory($fullEquipmentCharacter, $filler, GameInventoryService::INVENTORY_SIZE - 1);
        $this->seedShopEquipmentCache($fullEquipmentCharacter, $equipmentDefinition, [
            'base_stats' => ['price' => 10],
            'buy_price' => 10,
        ]);

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

        $unitSellPrice = $stackItem->calculateSellPrice();

        $first = $this->service->sellItem($character, $stackItem->id, 2);
        $second = $this->service->sellItem($character->fresh(), $singleItem->id, 1);

        $this->assertSame(100 + $unitSellPrice * 2, $first['copper']);
        $this->assertSame($unitSellPrice * 2, $first['sell_price']);
        $this->assertSame(1, $stackItem->fresh()->quantity);
        $this->assertSame(100 + $unitSellPrice * 3, $second['copper']);
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

    public function test_get_shop_items_limits_each_category_to_five_items(): void
    {
        Cache::flush();
        config([
            'game.shop.items_per_category_min' => 3,
            'game.shop.items_per_category_max' => 5,
        ]);
        $this->service = new GameShopService;
        $character = $this->createCharacter(['level' => 20]);

        foreach (range(1, 8) as $index) {
            $this->createItemDefinition([
                'name' => "测试武器{$index}",
                'type' => 'weapon',
                'sub_type' => 'sword',
                'required_level' => 10,
                'base_stats' => ['attack' => $index],
            ]);
        }

        $result = $this->service->getShopItems($character);
        $weaponCount = $result['items']->where('type', 'weapon')->count();

        $this->assertGreaterThanOrEqual(3, $weaponCount);
        $this->assertLessThanOrEqual(5, $weaponCount);
    }

    public function test_get_shop_items_generates_at_least_three_listings_with_single_template(): void
    {
        config([
            'game.shop.items_per_category_min' => 3,
            'game.shop.items_per_category_max' => 5,
        ]);
        $character = $this->createCharacter(['level' => 20]);
        $this->createItemDefinition([
            'name' => '唯一长剑',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'required_level' => 10,
            'base_stats' => ['attack' => 12],
        ]);

        $result = $this->service->getShopItems($character);
        $weapons = $result['items']->where('type', 'weapon')->values();

        $this->assertGreaterThanOrEqual(3, $weapons->count());
        $this->assertLessThanOrEqual(5, $weapons->count());
        $this->assertSame($weapons->count(), $weapons->pluck('listing_id')->unique()->count());
    }

    public function test_manual_refresh_disabled_throws(): void
    {
        config(['game.shop.manual_refresh_enabled' => false]);
        $character = $this->createCharacter(['copper' => 500]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('商店手动刷新暂未开放');

        $this->service->refreshShop($character);
    }

    public function test_shop_gems_roll_stats_within_configured_ranges(): void
    {
        config([
            'game.shop.equipment_count_min' => 0,
            'game.shop.equipment_count_max' => 0,
            'game.shop.gem_stat_ranges' => [
                'attack' => [8, 12],
            ],
        ]);
        $character = $this->createCharacter(['level' => 10]);
        $this->createItemDefinition([
            'name' => '攻击宝石',
            'type' => 'gem',
            'gem_stats' => ['attack' => 10],
            'required_level' => 1,
        ]);

        $result = $this->service->getShopItems($character);
        $attackGem = $result['items']->firstWhere('type', 'gem');

        $this->assertNotNull($attackGem);
        $attack = (int) ($attackGem['base_stats']['attack'] ?? 0);
        $this->assertGreaterThanOrEqual(8, $attack);
        $this->assertLessThanOrEqual(12, $attack);
    }

    public function test_buy_gem_uses_cached_rolled_stats(): void
    {
        config([
            'game.shop.equipment_count_min' => 0,
            'game.shop.equipment_count_max' => 0,
        ]);
        $character = $this->createCharacter(['level' => 10, 'copper' => 10000]);
        $attackGem = $this->createItemDefinition([
            'name' => '攻击宝石',
            'type' => 'gem',
            'gem_stats' => ['attack' => 10],
            'required_level' => 1,
        ]);

        $shop = $this->service->getShopItems($character);
        $listedGem = $shop['items']->firstWhere('id', $attackGem->id);
        $this->assertNotNull($listedGem);

        $this->service->buyItem($character, $attackGem->id, 1, null, (string) $listedGem['listing_id']);

        $item = GameItem::query()
            ->where('character_id', $character->id)
            ->whereHas('definition', fn ($query) => $query->where('type', 'gem'))
            ->with('definition')
            ->first();

        $this->assertNotNull($item);
        $this->assertSame(
            $listedGem['base_stats'],
            GameItem::normalizeStatsPrecision($item->definition->gem_stats ?? [])
        );
    }

    public function test_get_shop_items_includes_fixed_gems_by_sub_type(): void
    {
        config([
            'game.shop.equipment_count_min' => 0,
            'game.shop.equipment_count_max' => 0,
        ]);
        $character = $this->createCharacter(['level' => 10]);
        $this->createItemDefinition([
            'name' => '低级攻击宝石',
            'type' => 'gem',
            'sub_type' => null,
            'gem_stats' => ['attack' => 5],
            'required_level' => 1,
        ]);
        $higherAttackGem = $this->createItemDefinition([
            'name' => '高级攻击宝石',
            'type' => 'gem',
            'sub_type' => null,
            'gem_stats' => ['attack' => 20],
            'required_level' => 8,
        ]);
        $defenseGem = $this->createItemDefinition([
            'name' => '防御宝石',
            'type' => 'gem',
            'sub_type' => null,
            'gem_stats' => ['defense' => 8],
            'required_level' => 1,
        ]);

        $result = $this->service->getShopItems($character);
        $gemItems = $result['items']->where('type', 'gem')->values();

        $this->assertGreaterThanOrEqual(1, $gemItems->count());
        $this->assertLessThanOrEqual(5, $gemItems->count());
        $this->assertTrue($gemItems->pluck('id')->contains($higherAttackGem->id));
        $this->assertTrue($gemItems->pluck('id')->contains($defenseGem->id));
    }

    public function test_buy_gem_removes_from_shop_until_refresh(): void
    {
        config([
            'game.shop.equipment_count_min' => 0,
            'game.shop.equipment_count_max' => 0,
        ]);
        $character = $this->createCharacter(['level' => 10, 'copper' => 10000]);
        $defenseGem = $this->createItemDefinition([
            'name' => '防御宝石',
            'type' => 'gem',
            'gem_stats' => ['defense' => 8],
            'required_level' => 1,
            'buy_price' => 50,
        ]);
        $attackGem = $this->createItemDefinition([
            'name' => '攻击宝石',
            'type' => 'gem',
            'gem_stats' => ['attack' => 10],
            'required_level' => 1,
            'buy_price' => 50,
        ]);

        $before = $this->service->getShopItems($character);
        $this->assertGreaterThanOrEqual(1, $before['items']->where('type', 'gem')->count());

        $gemListing = $before['items']->where('type', 'gem')->first();
        $this->assertNotNull($gemListing);
        $listingId = (string) $gemListing['listing_id'];

        $this->service->buyItem($character, (int) $gemListing['id'], 1, null, $listingId);

        $after = $this->service->getShopItems($character);
        $this->assertFalse($after['items']->pluck('listing_id')->contains($listingId));
        $this->assertContains($listingId, $after['purchased']);
    }

    public function test_shop_equipment_stats_value_is_at_least_equipped_item_value(): void
    {
        $calculator = new InventoryItemCalculator;
        $character = $this->createCharacter(['level' => 20]);
        $equippedDefinition = $this->createItemDefinition([
            'name' => '已装备长剑',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'base_stats' => ['attack' => 5],
        ]);
        $equippedItem = $this->createItem($character, $equippedDefinition, [
            'stats' => ['attack' => 5],
            'quality' => 'common',
            'slot_index' => 0,
        ]);
        $character->equipment()->where('slot', 'weapon')->update(['item_id' => $equippedItem->id]);
        $equippedValue = $calculator->calculateItemValue($equippedItem->fresh()->load('definition'));

        $shopDefinition = $this->createItemDefinition([
            'name' => '廉价商店剑',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'required_level' => 20,
            'base_stats' => ['attack' => 1],
            'buy_price' => 0,
        ]);
        $this->seedShopEquipmentCache($character, $shopDefinition, [
            'base_stats' => ['attack' => 1],
            'quality' => 'common',
            'buy_price' => 1,
        ]);

        $result = $this->service->getShopItems($character);
        $shopItem = $result['items']->firstWhere('id', $shopDefinition->id);

        $this->assertNotNull($shopItem);
        $shopValue = $calculator->calculateEquipmentValue(
            $shopDefinition,
            $shopItem['base_stats'],
            $shopItem['quality'],
        );
        $this->assertGreaterThanOrEqual($equippedValue, $shopValue);
        $this->assertGreaterThan(1, $shopItem['base_stats']['attack'] ?? 0);
        $this->assertLessThanOrEqual(
            $calculator->resolveMaxShopStats($shopDefinition, $shopItem['quality'])['attack'],
            $shopItem['base_stats']['attack'] ?? 0,
        );
    }

    public function test_shop_does_not_offer_low_level_equipment_far_below_character_level(): void
    {
        $character = $this->createCharacter(['level' => 30]);
        $lowLevelDefinition = $this->createItemDefinition([
            'name' => '新手剑',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'required_level' => 1,
            'base_stats' => ['attack' => 5],
            'buy_price' => 0,
        ]);
        $this->createItemDefinition([
            'name' => '精钢剑',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'required_level' => 25,
            'base_stats' => ['attack' => 30],
            'buy_price' => 0,
        ]);

        Cache::flush();
        $result = $this->service->getShopItems($character);
        $weaponIds = $result['items']
            ->filter(fn ($item) => $item['type'] === 'weapon')
            ->pluck('id')
            ->all();

        $this->assertNotContains($lowLevelDefinition->id, $weaponIds);
    }

    public function test_shop_refresh_spreads_equipment_prices_up_to_double_equipped_value(): void
    {
        config([
            'game.shop.equipment_count_min' => 12,
            'game.shop.equipment_count_max' => 12,
            'game.shop.value_ceiling_multiplier' => 2.0,
        ]);

        $calculator = new InventoryItemCalculator;
        $character = $this->createCharacter(['level' => 20]);
        $equippedDefinition = $this->createItemDefinition([
            'name' => '已装备长剑',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'base_stats' => ['attack' => 10],
        ]);
        $equippedItem = $this->createItem($character, $equippedDefinition, [
            'stats' => ['attack' => 10],
            'quality' => 'common',
            'slot_index' => 0,
        ]);
        $character->equipment()->where('slot', 'weapon')->update(['item_id' => $equippedItem->id]);
        $equippedValue = $calculator->calculateItemValue($equippedItem->fresh()->load('definition'));

        for ($i = 0; $i < 5; $i++) {
            foreach (range(1, 8) as $weaponIndex) {
                $this->createItemDefinition([
                    'name' => "商店武器{$i}_{$weaponIndex}",
                    'type' => 'weapon',
                    'sub_type' => 'sword',
                    'required_level' => 20,
                    'base_stats' => ['attack' => 1],
                    'buy_price' => 0,
                ]);
            }

            Cache::flush();
            $this->service = new GameShopService;
            $result = $this->service->getShopItems($character);

            $weaponPrices = $result['items']
                ->filter(fn ($item) => $item['type'] === 'weapon')
                ->pluck('buy_price')
                ->all();

            $this->assertNotEmpty($weaponPrices);
            foreach ($weaponPrices as $buyPrice) {
                $this->assertGreaterThanOrEqual($equippedValue, $buyPrice);
            }

            $uniquePrices = array_values(array_unique($weaponPrices));
            if (count($uniquePrices) >= 2 && max($weaponPrices) > $equippedValue + 5) {
                return;
            }
        }

        $this->fail('Expected shop weapons to spread above the equipped value floor instead of clustering at it');
    }

    private function shopCacheKey(GameCharacter $character): string
    {
        return sprintf('game_shop_v2_%s', $character->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedShopEquipmentCache(GameCharacter $character, GameItemDefinition $definition, array $overrides = []): void
    {
        $listingId = ($definition->type ?? 'equipment') . ':' . $definition->id . ':0';

        Cache::put($this->shopCacheKey($character), [
            'equipment' => [array_merge([
                'id' => $definition->id,
                'listing_id' => $listingId,
                'name' => $definition->name,
                'type' => $definition->type,
                'sub_type' => $definition->sub_type,
                'base_stats' => $definition->base_stats,
                'quality' => 'common',
                'required_level' => $definition->required_level,
                'icon' => $definition->icon,
                'description' => $definition->description,
                'buy_price' => 100,
            ], $overrides)],
            'gems' => [],
            'refreshed_at' => time(),
        ], 1800);
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
        $cacheKey = $this->shopCacheKey($character);
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

        $this->assertGreaterThanOrEqual(1, $result['items']->count());
        $this->assertTrue($result['items']->every(fn (array $item): bool => $item['id'] === $potion->id));
    }

    public function test_buy_item_with_single_quantity(): void
    {
        $character = $this->createCharacter(['copper' => 1000]);
        $definition = $this->createItemDefinition([
            'name' => '测试药水',
            'type' => 'potion',
            'sub_type' => 'hp',
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

        $hpPotions = $result['items']->filter(fn ($item) => $item['type'] === 'potion' && $item['sub_type'] === 'hp');
        $this->assertEqualsCanonicalizing(
            [$lowHpPotion->id, $midHpPotion->id, $highHpPotion->id],
            $hpPotions->pluck('id')->all(),
        );
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

        $shop = $this->service->getShopItems($character);
        $listing = $shop['items']->firstWhere('id', $equipment->id);
        $this->assertNotNull($listing);
        $listingKey = (string) $listing['listing_id'];
        $this->service->recordPurchasedItem($character, $listingKey);

        $result = $this->service->getShopItems($character);
        $this->assertContains($listingKey, $result['purchased']);
    }

    public function test_buy_item_with_exact_copper_amount(): void
    {
        $character = $this->createCharacter(['copper' => 100]);
        $definition = $this->createItemDefinition([
            'name' => '精确价格药水',
            'type' => 'potion',
            'sub_type' => 'hp',
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
        ]);
        $expectedSellPrice = $item->calculateSellPrice();

        $result = $this->service->sellItem($character, $item->id, 1);

        $this->assertEquals($expectedSellPrice, $result['sell_price']);
        $this->assertEquals($expectedSellPrice, $result['copper']);
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
            'buy_price' => 0,
            'base_stats' => ['price' => 1000],
        ]);
        $this->seedShopEquipmentCache($character, $definition, [
            'base_stats' => ['price' => 1000],
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
            'stats' => ['max_hp' => 40],
        ]);
        $unitSellPrice = $item->calculateSellPrice();

        $result = $this->service->sellItem($character, $item->id, 5);

        $this->assertEquals(50 + $unitSellPrice * 5, $result['copper']);
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
        // 头盔不应有攻击属性
        $this->assertArrayNotHasKey('attack', $helmetItem['base_stats']);
        $this->assertArrayNotHasKey('crit_rate', $helmetItem['base_stats']);
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
        // 靴子不应有敏捷或攻击属性
        $this->assertArrayNotHasKey('dexterity', $bootsItem['base_stats']);
        $this->assertArrayNotHasKey('attack', $bootsItem['base_stats']);
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
        // 腰带不应有攻击或暴击属性
        $this->assertArrayNotHasKey('attack', $beltItem['base_stats']);
        $this->assertArrayNotHasKey('crit_rate', $beltItem['base_stats']);
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

        $timezone = (string) config('app.timezone', 'UTC');
        $yesterday = Carbon::now($timezone)->subDay()->startOfDay()->getTimestamp();

        Cache::put($this->shopCacheKey($character), [
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
            'gems' => [],
            'refreshed_at' => $yesterday,
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
        $this->assertArrayHasKey('attack', $ringStats);
        // 戒指不应有防御属性
        $this->assertArrayNotHasKey('defense', $ringStats);
        $this->assertArrayNotHasKey('max_hp', $ringStats);
        $this->assertArrayNotHasKey('max_mana', $ringStats);

        $amulet = $this->createItemDefinition([
            'name' => '测试项链',
            'type' => 'amulet',
            'required_level' => 8,
        ]);
        $amuletStats = $calculator->generateRandomStats($amulet);
        $this->assertArrayHasKey('attack', $amuletStats);
        // 项链不应有防御属性
        $this->assertArrayNotHasKey('defense', $amuletStats);
        $this->assertArrayNotHasKey('max_hp', $amuletStats);
        $this->assertArrayNotHasKey('max_mana', $amuletStats);

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

        $statBasedItem = $this->createItemDefinition([
            'name' => '属性计价物品',
            'buy_price' => 0,
            'base_stats' => ['attack' => 1],
            'required_level' => 10,
            'type' => 'weapon',
        ]);

        $gameItem = new GameItem([
            'stats' => ['attack' => 10],
            'quality' => 'mythic',
        ]);
        $gameItem->setRelation('definition', $statBasedItem);

        $buyPrice = $calculator->calculateBuyPrice($statBasedItem, ['attack' => 10], 'mythic');
        $sellPrice = $calculator->calculateSellPrice($gameItem);

        $this->assertSame($sellPrice * 2, $buyPrice);
        $this->assertGreaterThan(0, $buyPrice);
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
        Cache::put($this->shopCacheKey($character), [
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
        $gloves = $this->createItemDefinition([
            'name' => '随机手套',
            'type' => 'gloves',
            'required_level' => 10,
        ]);

        $helmetHasOffense = false;
        $ringHasDefense = false;
        $bootsHasOffense = false;
        $bootsHasDexterity = false;
        $amuletHasDefense = false;
        $glovesHasOffense = false;

        for ($i = 0; $i < 500; $i++) {
            $helmetStats = $calculator->generateRandomStats($helmet);
            if (isset($helmetStats['attack']) || isset($helmetStats['crit_rate']) || isset($helmetStats['crit_damage'])) {
                $helmetHasOffense = true;
            }

            $ringStats = $calculator->generateRandomStats($ring);
            if (isset($ringStats['defense']) || isset($ringStats['max_hp']) || isset($ringStats['max_mana'])) {
                $ringHasDefense = true;
            }

            $bootsStats = $calculator->generateRandomStats($boots);
            if (isset($bootsStats['attack']) || isset($bootsStats['crit_rate']) || isset($bootsStats['crit_damage'])) {
                $bootsHasOffense = true;
            }
            if (isset($bootsStats['dexterity'])) {
                $bootsHasDexterity = true;
            }

            $amuletStats = $calculator->generateRandomStats($amulet);
            if (isset($amuletStats['defense']) || isset($amuletStats['max_hp']) || isset($amuletStats['max_mana'])) {
                $amuletHasDefense = true;
            }

            $glovesStats = $calculator->generateRandomStats($gloves);
            if (isset($glovesStats['attack']) || isset($glovesStats['crit_rate']) || isset($glovesStats['crit_damage'])) {
                $glovesHasOffense = true;
            }
        }

        $this->assertFalse($helmetHasOffense, '头盔不应生成攻击/暴击属性');
        $this->assertFalse($ringHasDefense, '戒指不应生成防御属性');
        $this->assertFalse($bootsHasOffense, '靴子不应生成攻击/暴击属性');
        $this->assertFalse($bootsHasDexterity, '靴子不应生成敏捷属性');
        $this->assertFalse($amuletHasDefense, '项链不应生成防御属性');
        $this->assertFalse($glovesHasOffense, '手套不应生成攻击/暴击属性');
    }

    public function test_calculate_buy_price_uses_ring_type_multiplier_for_stats(): void
    {
        $calculator = new InventoryItemCalculator;

        $item = $this->createItemDefinition([
            'name' => '属性戒指',
            'type' => 'ring',
            'required_level' => 1,
            'buy_price' => 0,
            'base_stats' => [],
        ]);

        $gameItem = new GameItem([
            'stats' => ['attack' => 10],
            'quality' => 'common',
        ]);
        $gameItem->setRelation('definition', $item);

        $buyPrice = $calculator->calculateBuyPrice($item, ['attack' => 10], 'common');
        $sellPrice = $calculator->calculateSellPrice($gameItem);

        $this->assertSame(30, $buyPrice);
        $this->assertSame(15, $sellPrice);
    }
}
