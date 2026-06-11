<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Services\Game\Traits\UsesDistributedLock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GameShopService
{
    use UsesDistributedLock;

    /** 商店装备列表缓存时间（秒） */
    private const SHOP_CACHE_TTL_SECONDS = 1800; // 30 分钟

    /** 强制刷新商店费用（铜币），1 银 = 100 铜 */
    public const REFRESH_COST_COPPER = 100;

    /** 幂等性缓存时间（秒），24 小时 */
    private const IDEMPOTENCY_CACHE_TTL_SECONDS = 86400;

    private const SHOP_CACHE_KEY_PREFIX = 'game_shop_v2_';

    private const PURCHASED_CACHE_KEY_PREFIX = 'game_shop_purchased_';

    /** 商店操作分布式锁超时时间（秒） */
    private const SHOP_LOCK_TIMEOUT_SECONDS = 10;

    public function __construct(
        private InventoryItemCalculator $itemCalculator = new InventoryItemCalculator,
        private ShopItemCreationService $itemCreationService = new ShopItemCreationService
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

        // 获取已购买的装备ID列表
        $purchasedItemIds = $this->getPurchasedItemIds($character);
        $equippedValueFloorsByType = $this->buildEquippedValueFloorsByType($character);

        if (is_array($cached) && isset($cached['equipment'], $cached['refreshed_at'])) {
            /** @var array<int, array<string,mixed>> $cachedEquipment */
            $cachedEquipment = is_array($cached['equipment']) ? $cached['equipment'] : [];
            $cachedRefreshed = is_numeric($cached['refreshed_at']) ? (int) $cached['refreshed_at'] : time();
            $randomEquipmentItems = collect($cachedEquipment)
                ->map(fn (array $item): array => $this->hydrateEquipmentListing($item, $equippedValueFloorsByType))
                ->filter(fn ($item) => ($item['required_level'] ?? 0) <= $character->level)
                ->filter(fn ($item) => ! in_array($item['id'], $purchasedItemIds))
                ->values();
            $nextRefreshAt = $cachedRefreshed + self::SHOP_CACHE_TTL_SECONDS;
        } else {
            $randomEquipmentItems = $this->buildRandomEquipmentItems($character, $equippedValueFloorsByType);
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
     * 获取已购买的物品ID列表
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
     * 记录已购买的物品ID
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
            $previewItem = new GameItem([
                'quality' => 'common',
                'stats' => $randomStats,
            ]);
            $previewItem->setRelation('definition', $definition);
            $sellPrice = $definition->buy_price > 0
                ? max(1, (int) ($buyPrice / 2))
                : $this->itemCalculator->calculateSellPrice($previewItem);

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
                'sell_price' => $sellPrice,
            ];
        });

        return $result;
    }

    /**
     * 构建随机装备列表
     *
     * @return Collection<int, array{id:int,name:string,type:string,sub_type:string|null,base_stats:array<string,mixed>,quality:string,required_level:int,icon:string|null,description:string|null,buy_price:int}>
     */
    /**
     * @param  array<string, int>  $equippedValueFloorsByType
     */
    private function buildRandomEquipmentItems(GameCharacter $character, array $equippedValueFloorsByType): Collection
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
        $result = $selectedEquipments->map(function ($definition) use ($equippedValueFloorsByType) {
            $valueFloor = $equippedValueFloorsByType[$definition->type] ?? 0;
            $roll = $this->rollShopEquipment($definition, $valueFloor);

            return [
                'id' => $definition->id,
                'name' => $definition->name,
                'type' => $definition->type,
                'sub_type' => $definition->sub_type,
                'base_stats' => GameItem::normalizeStatsPrecision($roll['stats']),
                'quality' => $roll['quality'],
                'required_level' => $definition->required_level,
                'icon' => $definition->icon,
                'description' => $definition->description,
                'buy_price' => $this->itemCalculator->calculateBuyPrice($definition, $roll['stats'], $roll['quality']),
            ];
        });

        return $result;
    }

    /**
     * 购买物品
     *
     * @return array{copper:int,total_price:int,quantity:int,item_name:string}
     */
    public function buyItem(GameCharacter $character, int $itemId, int $quantity = 1, ?string $idempotencyKey = null): array
    {
        $isIdempotentRequest = $idempotencyKey !== null && $idempotencyKey !== '';

        if ($isIdempotentRequest) {
            return $this->executeWithIdempotency(
                characterId: $character->id,
                operation: 'buy',
                idempotencyKey: $idempotencyKey,
                callback: fn () => $this->performBuy($character, $itemId, $quantity),
                idempotencyTtlSeconds: self::IDEMPOTENCY_CACHE_TTL_SECONDS,
            );
        }

        return $this->executeWithDistributedLock(
            lockKey: 'shop:lock:buy:' . $character->id . ':' . $itemId,
            callback: fn () => $this->performBuy($character, $itemId, $quantity),
            timeoutSeconds: self::SHOP_LOCK_TIMEOUT_SECONDS,
        );
    }

    /**
     * 执行购买逻辑
     *
     * @return array{copper:int,total_price:int,quantity:int,item_name:string}
     */
    private function performBuy(GameCharacter $character, int $itemId, int $quantity): array
    {
        $definition = GameItemDefinition::find($itemId);

        if (! $definition || ! $definition->is_active) {
            throw new \InvalidArgumentException('物品不存在或不可购买');
        }

        if ($character->level < $definition->required_level) {
            throw new \InvalidArgumentException("需要等级 {$definition->required_level}");
        }

        $isPotion = $definition->type === 'potion';
        $equippedValueFloorsByType = $isPotion ? [] : $this->buildEquippedValueFloorsByType($character);
        $cachedShopItem = $isPotion ? null : $this->findCachedShopItem($character, $itemId, $equippedValueFloorsByType);
        if ($cachedShopItem !== null) {
            /** @var array<string, int|float> $randomStats */
            $randomStats = is_array($cachedShopItem['base_stats'] ?? null) ? $cachedShopItem['base_stats'] : [];
            $quality = is_string($cachedShopItem['quality'] ?? null) ? $cachedShopItem['quality'] : 'common';
            $unitPrice = (int) ($cachedShopItem['buy_price'] ?? 0);
        } elseif ($isPotion) {
            $randomStats = $this->itemCalculator->generateRandomStats($definition);
            $quality = 'common';
            $unitPrice = $this->itemCalculator->calculateBuyPrice($definition, $randomStats, $quality);
        } else {
            $valueFloor = $equippedValueFloorsByType[$definition->type] ?? 0;
            $roll = $this->rollShopEquipment($definition, $valueFloor);
            $randomStats = $roll['stats'];
            $quality = $roll['quality'];
            $unitPrice = $this->itemCalculator->calculateBuyPrice($definition, $randomStats, $quality);
        }

        $totalPrice = $unitPrice * $quantity;

        if ($character->copper < $totalPrice) {
            throw new \InvalidArgumentException('货币不足');
        }

        return DB::transaction(function () use ($character, $definition, $randomStats, $quality, $totalPrice, $quantity, $itemId, $isPotion) {
            // 检查背包空间
            if (! $this->itemCreationService->hasInventorySpace($character, $quantity, $isPotion)) {
                throw new \InvalidArgumentException($isPotion ? '背包已满' : '背包空间不足');
            }

            // 药品处理
            if ($isPotion) {
                $this->itemCreationService->addPotionToInventory($character, $definition, $quantity, $randomStats);
            } else {
                // 装备类物品
                $this->itemCreationService->createEquipmentItems($character, $definition, $quantity, $randomStats, $quality);

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
    public function sellItem(GameCharacter $character, int $itemId, int $quantity = 1, ?string $idempotencyKey = null): array
    {
        $isIdempotentRequest = $idempotencyKey !== null && $idempotencyKey !== '';

        if ($isIdempotentRequest) {
            return $this->executeWithIdempotency(
                characterId: $character->id,
                operation: 'sell',
                idempotencyKey: $idempotencyKey,
                callback: fn () => $this->performSell($character, $itemId, $quantity),
                idempotencyTtlSeconds: self::IDEMPOTENCY_CACHE_TTL_SECONDS,
            );
        }

        return $this->executeWithDistributedLock(
            lockKey: 'shop:lock:sell:' . $character->id . ':' . $itemId,
            callback: fn () => $this->performSell($character, $itemId, $quantity),
            timeoutSeconds: self::SHOP_LOCK_TIMEOUT_SECONDS,
        );
    }

    /**
     * 执行出售逻辑
     *
     * @return array{copper:int,sell_price:int,quantity:int,item_name:string}
     */
    private function performSell(GameCharacter $character, int $itemId, int $quantity): array
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

    /**
     * 从商店缓存中查找当前展示的装备（保证购买价与列表一致）
     *
     * @param  array<string, int>  $equippedValueFloorsByType
     * @return array<string, mixed>|null
     */
    private function findCachedShopItem(GameCharacter $character, int $definitionId, array $equippedValueFloorsByType): ?array
    {
        $cacheKey = $this->getShopCacheKey($character);
        $cached = cache()->get($cacheKey);

        if (! is_array($cached)) {
            return null;
        }

        $equipment = $cached['equipment'] ?? null;
        if (! is_array($equipment)) {
            return null;
        }

        foreach ($equipment as $item) {
            if (! is_array($item)) {
                continue;
            }
            if ((int) ($item['id'] ?? 0) === $definitionId) {
                return $this->hydrateEquipmentListing($item, $equippedValueFloorsByType);
            }
        }

        return null;
    }

    /**
     * 各装备类型已穿戴物品的估价下限（同类型取最高，与商店属性计价一致）
     *
     * @return array<string, int>
     */
    private function buildEquippedValueFloorsByType(GameCharacter $character): array
    {
        $floors = [];

        $equipmentSlots = $character->equipment()
            ->with(['item.definition', 'item.gems'])
            ->get();

        foreach ($equipmentSlots as $slot) {
            $item = $slot->item;
            if (! $item instanceof GameItem || ! $item->definition instanceof GameItemDefinition) {
                continue;
            }

            $type = $item->definition->type;
            $itemValue = $this->itemCalculator->calculateItemValue($item);
            $floors[$type] = max($floors[$type] ?? 0, $itemValue);
        }

        return $floors;
    }

    /**
     * 随机生成商店装备，并保证属性估价不低于已装备下限
     *
     * @return array{stats: array<string, int|float>, quality: string}
     */
    private function rollShopEquipment(GameItemDefinition $definition, int $valueFloor): array
    {
        $stats = $this->itemCalculator->generateRandomStats($definition);
        $quality = $this->itemCalculator->generateRandomQuality($definition->required_level);
        $stats = $this->itemCalculator->ensureStatsMeetValueFloor($definition, $stats, $quality, $valueFloor);

        return [
            'stats' => $stats,
            'quality' => $quality,
        ];
    }

    /**
     * 按当前已装备估价下限校正缓存中的商店装备属性与标价
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $equippedValueFloorsByType
     * @return array<string, mixed>
     */
    private function hydrateEquipmentListing(array $item, array $equippedValueFloorsByType): array
    {
        $definitionId = (int) ($item['id'] ?? 0);
        if ($definitionId <= 0) {
            return $item;
        }

        $definition = GameItemDefinition::query()->find($definitionId);
        if (! $definition) {
            return $item;
        }

        $stats = is_array($item['base_stats'] ?? null) ? $item['base_stats'] : [];
        $quality = is_string($item['quality'] ?? null) ? $item['quality'] : 'common';
        $valueFloor = $equippedValueFloorsByType[$definition->type] ?? 0;
        $stats = $this->itemCalculator->ensureStatsMeetValueFloor($definition, $stats, $quality, $valueFloor);
        $item['base_stats'] = GameItem::normalizeStatsPrecision($stats);
        $item['buy_price'] = $this->itemCalculator->calculateBuyPrice($definition, $stats, $quality);

        return $item;
    }
}
