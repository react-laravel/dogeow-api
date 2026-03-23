<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GameShopService
{
    /** 商店装备列表缓存时间(秒) */
    private const SHOP_CACHE_TTL_SECONDS = 1800; // 30 分钟

    /** 强制刷新商店费用(铜币)，1 银 = 100 铜 */
    public const REFRESH_COST_COPPER = 100;

    private const SHOP_CACHE_KEY_PREFIX = 'game_shop_';

    private const PURCHASED_CACHE_KEY_PREFIX = 'game_shop_purchased_';

    public function __construct(
        private InventoryItemCalculator $itemCalculator = new InventoryItemCalculator
    ) {}

    /**
     * 清除当前角色的商店装备缓存
     */
    public function clearShopCache(GameCharacter $character): void
    {
        Cache::forget($this->getShopCacheKey($character));
    }

    /**
     * 强制刷新商店：扣除 1 银币后清除缓存并返回新列表
     *
     * @return array{items: Collection<int, array<string,mixed>>, player_copper: int, next_refresh_at: int}
     */
    public function refreshShop(GameCharacter $character): array
    {
        if ($character->copper < self::REFRESH_COST_COPPER) {
            throw new \InvalidArgumentException('货币不足，强制刷新需要 1 银币');
        }

        $character->copper -= self::REFRESH_COST_COPPER;
        $character->save();

        $this->clearShopCache($character);

        return $this->getShopItems($character);
    }

    /**
     * 获取商店物品列表
     *
     * @return array{items: Collection<int, array<string,mixed>>, player_copper: int, next_refresh_at: int, purchased: array<int>}
     */
    public function getShopItems(GameCharacter $character): array
    {
        // 药品固定显示
        $fixedPotionItems = $this->buildFixedPotionItems($character);

        // 装备列表使用缓存
        $cacheKey = $this->getShopCacheKey($character);
        $cached = cache()->get($cacheKey, []);
        $equipmentArray = [];
        if (is_array($cached) && isset($cached['equipment']) && is_array($cached['equipment'])) {
            $equipmentArray = $cached['equipment'];
        }
        $equipment = collect($equipmentArray);

        // 获取已购买的装备 ID 列表
        $purchasedItemIds = $this->getPurchasedItemIds($character);

        if (is_array($cached) && isset($cached['equipment'], $cached['refreshed_at'])) {
            /** @var array<int, array<string,mixed>> $cachedEquipment */
            $cachedEquipment = is_array($cached['equipment']) ? $cached['equipment'] : [];
            $cachedRefreshed = is_numeric($cached['refreshed_at']) ? (int) $cached['refreshed_at'] : time();
            $randomEquipmentItems = collect($cachedEquipment)
                ->filter(fn ($item) => ($item['required_level'] ?? 0) <= $character->level)
                ->filter(fn ($item) => ! in_array($item['id'], $purchasedItemIds))
                ->values();
            $nextRefreshAt = $cachedRefreshed + self::SHOP_CACHE_TTL_SECONDS;
        } else {
            $randomEquipmentItems = $this->buildRandomEquipmentItems($character);
            $refreshedAt = time();
            Cache::put($cacheKey, [
                'equipment' => $randomEquipmentItems->values()->all(),
                'refreshed_at' => $refreshedAt,
            ], self::SHOP_CACHE_TTL_SECONDS);
            $nextRefreshAt = $refreshedAt + self::SHOP_CACHE_TTL_SECONDS;
            // 新缓存时清空已购买记录
            $this->clearPurchasedItems($character);
        }

        $shopItems = $fixedPotionItems->concat($randomEquipmentItems);

        $refreshedAt = time();
        if (is_array($cached) && isset($cached['refreshed_at']) && is_numeric($cached['refreshed_at'])) {
            $refreshedAt = (int) $cached['refreshed_at'];
        }

        return [
            'items' => $shopItems,
            'player_copper' => (int) $character->copper,
            'next_refresh_at' => $nextRefreshAt,
            'purchased' => $purchasedItemIds,
        ];
    }

    /**
     * 获取已购买的物品 ID 列表
     *
     * @return int[]
     */
    private function getPurchasedItemIds(GameCharacter $character): array
    {
        $cacheKey = $this->getPurchasedCacheKey($character);
        $purchased = Cache::get($cacheKey);

        if (! is_array($purchased)) {
            return [];
        }

        $shopCacheKey = $this->getShopCacheKey($character);
        $shopCache = Cache::get($shopCacheKey);

        if (! is_array($shopCache) || ! isset($shopCache['refreshed_at'])) {
            return [];
        }

        $cacheAge = time() - (int) $shopCache['refreshed_at'];
        if ($cacheAge > self::SHOP_CACHE_TTL_SECONDS) {
            $this->clearPurchasedItems($character);

            return [];
        }

        return array_values(array_map(static fn ($v): int => (int) $v, $purchased));
    }

    /**
     * 记录已购买的物品 ID
     */
    public function recordPurchasedItem(GameCharacter $character, int $definitionId): void
    {
        $cacheKey = $this->getPurchasedCacheKey($character);
        $purchased = Cache::get($cacheKey);

        if (! is_array($purchased)) {
            $purchased = [];
        }

        if (! in_array($definitionId, $purchased)) {
            $purchased[] = $definitionId;
            Cache::put($cacheKey, $purchased, self::SHOP_CACHE_TTL_SECONDS);
        }
    }

    /**
     * 清空已购买记录
     */
    private function clearPurchasedItems(GameCharacter $character): void
    {
        Cache::forget($this->getPurchasedCacheKey($character));
    }

    /**
     * 构建固定药品列表
     *
     * @return Collection<int, array{id:int,name:string,type:string,sub_type:string|null,base_stats:array<string,mixed>,required_level:int,icon:string|null,description:string|null,buy_price:int,sell_price:int}>
     */
    private function buildFixedPotionItems(GameCharacter $character): Collection
    {
        $potionDefinitions = GameItemDefinition::query()
            ->where('is_active', true)
            ->where('type', 'potion')
            ->where('required_level', '<=', $character->level)
            ->orderBy('sub_type')
            ->orderByDesc('required_level')
            ->get();

        $fixedPotions = $potionDefinitions->unique('sub_type')->values();

        /** @var Collection<int, array{id:int,name:string,type:string,sub_type:string|null,base_stats:array<string,mixed>,required_level:int,icon:string|null,description:string|null,buy_price:int,sell_price:int}> $result */
        $result = $fixedPotions->map(function ($definition) {
            $randomStats = $this->itemCalculator->generateRandomStats($definition);
            $buyPrice = $this->itemCalculator->calculateBuyPrice($definition, $randomStats);

            return [
                'id' => $definition->id,
                'name' => $definition->name,
                'type' => $definition->type,
                'sub_type' => $definition->sub_type,
                'base_stats' => GameItem::normalizeStatsPrecision($randomStats),
                'required_level' => $definition->required_level,
                'icon' => $definition->icon,
                'description' => $definition->description,
                'buy_price' => $buyPrice,
                'sell_price' => (int) floor($buyPrice * (float) config('game.shop.sell_ratio', 0.3)),
            ];
        });

        return $result;
    }

    /**
     * 构建随机装备列表
     *
     * @return Collection<int, array{id:int,name:string,type:string,sub_type:string|null,base_stats:array<string,mixed>,quality:string,required_level:int,icon:string|null,description:string|null,buy_price:int}>
     */
    private function buildRandomEquipmentItems(GameCharacter $character): Collection
    {
        $equipmentDefinitions = GameItemDefinition::query()
            ->where('is_active', true)
            ->where('type', '!=', 'potion')
            ->where('type', '!=', 'amulet')
            ->where('required_level', '<=', $character->level)
            ->orderBy('type')
            ->orderBy('required_level')
            ->get();

        $shopSizeMin = (int) config('game.shop.equipment_count_min', 20);
        $shopSizeMax = (int) config('game.shop.equipment_count_max', 25);
        $shopSize = rand($shopSizeMin, $shopSizeMax);
        $selectedEquipments = $equipmentDefinitions->shuffle()->take($shopSize);

        /** @var Collection<int, array{id:int,name:string,type:string,sub_type:string|null,base_stats:array<string,mixed>,quality:string,required_level:int,icon:string|null,description:string|null,buy_price:int}> $result */
        $result = $selectedEquipments->map(function ($definition) {
            $randomStats = $this->itemCalculator->generateRandomStats($definition);
            $quality = $this->itemCalculator->generateRandomQuality($definition->required_level);

            return [
                'id' => $definition->id,
                'name' => $definition->name,
                'type' => $definition->type,
                'sub_type' => $definition->sub_type,
                'base_stats' => GameItem::normalizeStatsPrecision($randomStats),
                'quality' => $quality,
                'required_level' => $definition->required_level,
                'icon' => $definition->icon,
                'description' => $definition->description,
                'buy_price' => $this->itemCalculator->calculateBuyPrice($definition, $randomStats, $quality),
            ];
        });

        return $result;
    }

    /**
     * 购买物品
     *
     * @return array{copper:int,total_price:int,quantity:int,item_name:string}
     */
    public function buyItem(GameCharacter $character, int $itemId, int $quantity = 1): array
    {
        $definition = GameItemDefinition::find($itemId);

        if (! $definition || ! $definition->is_active) {
            throw new \InvalidArgumentException('物品不存在或不可购买');
        }

        if ($character->level < $definition->required_level) {
            throw new \InvalidArgumentException("需要等级 {$definition->required_level}");
        }

        // 生成随机属性
        $randomStats = $this->itemCalculator->generateRandomStats($definition);

        // 计算总价
        $totalPrice = $this->itemCalculator->calculateBuyPrice($definition, $randomStats) * $quantity;

        if ($character->copper < $totalPrice) {
            throw new \InvalidArgumentException('货币不足');
        }

        return DB::transaction(function () use ($character, $definition, $randomStats, $totalPrice, $quantity, $itemId) {
            $inventoryCount = $character->items()->where('is_in_storage', false)->count();
            $inventorySize = GameInventoryService::INVENTORY_SIZE;

            // 药品处理
            if ($definition->type === 'potion') {
                /** @var GameItem|null $existingItem */
                $existingItem = $character->items()
                    ->where('definition_id', $definition->id)
                    ->where('is_in_storage', false)
                    ->where('quality', 'common')
                    ->first();

                if ($existingItem) {
                    $existingItem->quantity += $quantity;
                    $existingItem->save();
                } else {
                    if ($inventoryCount >= $inventorySize) {
                        throw new \InvalidArgumentException('背包已满');
                    }

                    $tempItem = new GameItem([
                        'character_id' => $character->id,
                        'definition_id' => $definition->id,
                        'quality' => 'common',
                        'stats' => $randomStats,
                        'affixes' => [],
                        'is_in_storage' => false,
                        'quantity' => $quantity,
                    ]);
                    $sellPrice = $this->itemCalculator->calculateSellPrice($tempItem);

                    GameItem::create([
                        'character_id' => $character->id,
                        'definition_id' => $definition->id,
                        'quality' => 'common',
                        'stats' => $randomStats,
                        'affixes' => [],
                        'is_in_storage' => false,
                        'quantity' => $quantity,
                        'slot_index' => (new GameInventoryService)->findEmptySlot($character, false),
                        'sell_price' => $sellPrice,
                    ]);
                }
            } else {
                // 装备类物品
                if ($inventoryCount + $quantity > $inventorySize) {
                    throw new \InvalidArgumentException('背包空间不足');
                }

                $tempItem = new GameItem([
                    'character_id' => $character->id,
                    'definition_id' => $definition->id,
                    'quality' => 'common',
                    'stats' => $randomStats,
                    'affixes' => [],
                    'is_in_storage' => false,
                    'quantity' => 1,
                ]);
                $sellPrice = $this->itemCalculator->calculateSellPrice($tempItem);

                $inventoryService = new GameInventoryService;
                for ($i = 0; $i < $quantity; $i++) {
                    GameItem::create([
                        'character_id' => $character->id,
                        'definition_id' => $definition->id,
                        'quality' => 'common',
                        'stats' => $randomStats,
                        'affixes' => [],
                        'is_in_storage' => false,
                        'quantity' => 1,
                        'slot_index' => $inventoryService->findEmptySlot($character, false),
                        'sell_price' => $sellPrice,
                    ]);
                }

                // 记录已购买的装备
                $this->recordPurchasedItem($character, $itemId);
            }

            // 扣除铜币
            $character->copper -= $totalPrice;
            $character->save();

            return [
                'copper' => $character->copper,
                'total_price' => $totalPrice,
                'quantity' => $quantity,
                'item_name' => $definition->name,
            ];
        });
    }

    /**
     * 出售物品
     *
     * @return array{copper:int,sell_price:int,quantity:int,item_name:string}
     */
    public function sellItem(GameCharacter $character, int $itemId, int $quantity = 1): array
    {
        $item = GameItem::query()
            ->where('id', $itemId)
            ->where('character_id', $character->id)
            ->with('definition')
            ->first();

        /** @var GameItem|null $item */
        if (! $item) {
            throw new \InvalidArgumentException('物品不存在或不属于你');
        }

        if ($item->is_in_storage) {
            throw new \InvalidArgumentException('请先将物品从仓库移到背包');
        }

        $equipped = $character->equipment()->where('item_id', $item->id)->exists();
        if ($equipped) {
            throw new \InvalidArgumentException('请先卸下装备');
        }

        if ($item->quantity < $quantity) {
            throw new \InvalidArgumentException('物品数量不足');
        }

        // 计算售价
        $sellPrice = $this->itemCalculator->calculateSellPrice($item) * $quantity;

        return DB::transaction(function () use ($character, $item, $quantity, $sellPrice) {
            $character->copper += $sellPrice;
            $character->save();

            if ($item->quantity > $quantity) {
                $item->quantity -= $quantity;
                $item->save();
            } else {
                $item->delete();
            }

            return [
                'copper' => $character->copper,
                'sell_price' => $sellPrice,
                'quantity' => $quantity,
                'item_name' => $item->definition->name,
            ];
        });
    }

    private function getShopCacheKey(GameCharacter $character): string
    {
        return self::SHOP_CACHE_KEY_PREFIX . $character->id;
    }

    private function getPurchasedCacheKey(GameCharacter $character): string
    {
        return self::PURCHASED_CACHE_KEY_PREFIX . $character->id;
    }
}
