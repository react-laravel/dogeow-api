<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Services\Game\Traits\UsesDistributedLock;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GameShopService
{
    use UsesDistributedLock;

    /** 强制刷新商店费用（铜币），1 银 = 100 铜 */
    public const REFRESH_COST_COPPER = 100;

    /** 幂等性缓存时间（秒），24 小时 */
    private const IDEMPOTENCY_CACHE_TTL_SECONDS = 86400;

    private const SHOP_CACHE_KEY_PREFIX = 'game_shop_v2_';

    private const PURCHASED_CACHE_KEY_PREFIX = 'game_shop_purchased_';

    /** 商店操作分布式锁超时时间（秒） */
    private const SHOP_LOCK_TIMEOUT_SECONDS = 10;

    /** 每个分类最少展示的商店物品数量 */
    private const SHOP_ITEMS_PER_CATEGORY_MIN = 3;

    /** 每个分类最多展示的商店物品数量 */
    private const SHOP_ITEMS_PER_CATEGORY_MAX = 5;

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
        if (! config('game.shop.manual_refresh_enabled', false)) {
            throw new \InvalidArgumentException('商店手动刷新暂未开放');
        }

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
     * @return array{items: Collection<int, array<string,mixed>>, player_copper: int, next_refresh_at: int, purchased: array<string>, manual_refresh_enabled: bool}
     */
    public function getShopItems(GameCharacter $character): array
    {
        $fixedPotionItems = $this->buildFixedPotionItems($character);

        $cacheKey = $this->getShopCacheKey($character);
        $cached = cache()->get($cacheKey, []);

        $purchasedListingKeys = $this->getPurchasedListingKeys($character);
        $equippedValueFloorsByType = $this->buildEquippedValueFloorsByType($character);

        if (is_array($cached) && isset($cached['equipment'], $cached['gems'], $cached['refreshed_at'])) {
            /** @var array<int, array<string,mixed>> $cachedEquipment */
            $cachedEquipment = is_array($cached['equipment']) ? $cached['equipment'] : [];
            /** @var array<int, array<string,mixed>> $cachedGems */
            $cachedGems = is_array($cached['gems']) ? $cached['gems'] : [];
            $cachedRefreshed = is_numeric($cached['refreshed_at']) ? (int) $cached['refreshed_at'] : time();
            $randomEquipmentItems = collect($cachedEquipment)
                ->map(fn (array $item): array => $this->hydrateEquipmentListing($item, $equippedValueFloorsByType))
                ->filter(fn ($item) => ($item['required_level'] ?? 0) <= $character->level)
                ->filter(fn ($item) => ! in_array($this->resolveListingKey($item), $purchasedListingKeys, true))
                ->values();
            $cachedGemItems = collect($cachedGems)
                ->filter(fn (array $item): bool => ($item['required_level'] ?? 0) <= $character->level)
                ->filter(fn (array $item): bool => ! in_array($this->resolveListingKey($item), $purchasedListingKeys, true))
                ->values();
            $nextRefreshAt = $this->getNextDailyRefreshTimestamp($cachedRefreshed);
        } else {
            $randomEquipmentItems = $this->buildRandomEquipmentItems($character, $equippedValueFloorsByType);
            $cachedGemItems = $this->buildCachedGemItems($character);
            $refreshedAt = time();
            $cacheTtl = $this->getSecondsUntilNextDailyRefresh($refreshedAt);
            Cache::put($cacheKey, [
                'equipment' => $randomEquipmentItems->values()->all(),
                'gems' => $cachedGemItems->values()->all(),
                'refreshed_at' => $refreshedAt,
            ], $cacheTtl);
            $nextRefreshAt = $this->getNextDailyRefreshTimestamp($refreshedAt);
            $this->clearPurchasedItems($character);
            $purchasedListingKeys = [];
        }

        $shopItems = $this->limitItemsPerCategory(
            $fixedPotionItems->concat($randomEquipmentItems)->concat($cachedGemItems),
        );

        return [
            'items' => $shopItems,
            'player_copper' => (int) $character->copper,
            'next_refresh_at' => $nextRefreshAt,
            'purchased' => $purchasedListingKeys,
            'manual_refresh_enabled' => (bool) config('game.shop.manual_refresh_enabled', false),
        ];
    }

    /**
     * 获取已购买的商店 listing 键列表
     *
     * @return string[]
     */
    private function getPurchasedListingKeys(GameCharacter $character): array
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

        $refreshedAt = (int) $shopCache['refreshed_at'];
        if (time() >= $this->getNextDailyRefreshTimestamp($refreshedAt)) {
            $this->clearPurchasedItems($character);

            return [];
        }

        return array_values(array_map(static fn ($v): string => (string) $v, $purchased));
    }

    /**
     * 记录已购买的商店 listing
     */
    public function recordPurchasedItem(GameCharacter $character, string $listingKey): void
    {
        $cacheKey = $this->getPurchasedCacheKey($character);
        $purchased = Cache::get($cacheKey);

        if (! is_array($purchased)) {
            $purchased = [];
        }

        if (! in_array($listingKey, $purchased, true)) {
            $purchased[] = $listingKey;
            $shopCache = Cache::get($this->getShopCacheKey($character));
            $refreshedAt = is_array($shopCache) && isset($shopCache['refreshed_at'])
                ? (int) $shopCache['refreshed_at']
                : time();
            Cache::put($cacheKey, $purchased, $this->getSecondsUntilNextDailyRefresh($refreshedAt));
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

        $potionPool = $potionDefinitions->unique('id')->values();

        return $this->buildUniqueCategoryListings($potionPool, function (GameItemDefinition $definition): array {
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
    }

    /**
     * 构建当日缓存的宝石列表（每种属性一颗，属性每日随机）
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildCachedGemItems(GameCharacter $character): Collection
    {
        $gemDefinitions = GameItemDefinition::query()
            ->where('is_active', true)
            ->where('type', 'gem')
            ->where('required_level', '<=', $character->level)
            ->orderByDesc('required_level')
            ->orderBy('id')
            ->get();

        $gemPool = $gemDefinitions
            ->unique(fn (GameItemDefinition $definition): string => $this->resolveGemStatKey($definition))
            ->values();

        return $this->buildUniqueCategoryListings($gemPool, function (GameItemDefinition $definition): array {
            $rolledStats = $this->itemCalculator->rollGemStats($definition);
            $buyPrice = $this->itemCalculator->calculateBuyPrice($definition, $rolledStats);
            $sellPrice = $this->itemCalculator->calculateGemSellPriceFromStats($rolledStats);

            return [
                'id' => $definition->id,
                'name' => $definition->name,
                'type' => $definition->type,
                'sub_type' => $definition->sub_type,
                'base_stats' => GameItem::normalizeStatsPrecision($rolledStats),
                'required_level' => $definition->required_level,
                'icon' => $definition->icon,
                'description' => $definition->description,
                'buy_price' => $buyPrice,
                'sell_price' => $sellPrice,
            ];
        });
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
        $levelSpan = max(0, (int) config('game.shop.min_required_level_span', 10));
        $minRequiredLevel = max(1, $character->level - $levelSpan);

        $equipmentDefinitions = GameItemDefinition::query()
            ->where('is_active', true)
            ->where('type', '!=', 'potion')
            ->where('type', '!=', 'gem')
            ->where('type', '!=', 'amulet')
            ->where('required_level', '>=', $minRequiredLevel)
            ->where('required_level', '<=', $character->level)
            ->orderBy('type')
            ->orderBy('required_level')
            ->get();

        /** @var Collection<int, array{id:int,name:string,type:string,sub_type:string|null,base_stats:array<string,mixed>,quality:string,required_level:int,icon:string|null,description:string|null,buy_price:int,listing_id:string}> $result */
        $result = new Collection;

        foreach ($equipmentDefinitions->groupBy('type') as $type => $definitions) {
            /** @var Collection<int, GameItemDefinition> $pool */
            $pool = $definitions->values();
            $typeKey = is_string($type) ? $type : 'equipment';

            $categoryListings = $this->buildCategoryListings(
                $pool,
                function (GameItemDefinition $definition) use ($equippedValueFloorsByType): array {
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
                },
                $typeKey,
            );

            $result = $result->concat($categoryListings);
        }

        return $result;
    }

    /**
     * @param  Collection<int, GameItemDefinition>  $pool
     * @param  callable(GameItemDefinition): array<string, mixed>  $buildListing
     * @return Collection<int, array<string, mixed>>
     */
    private function buildCategoryListings(
        Collection $pool,
        callable $buildListing,
        ?string $listingCategory = null,
    ): Collection {
        if ($pool->isEmpty()) {
            return collect();
        }

        $targetCount = random_int($this->shopItemsPerCategoryMin(), $this->shopItemsPerCategoryMax());
        /** @var Collection<int, array<string, mixed>> $result */
        $result = new Collection;

        for ($index = 0; $index < $targetCount; $index++) {
            /** @var GameItemDefinition $definition */
            $definition = $pool->random();
            $listing = $buildListing($definition);
            $category = $listingCategory ?? (string) ($listing['type'] ?? $definition->type);
            $listing['listing_id'] = $this->makeListingId($category, $definition->id, $index);
            $result->push($listing);
        }

        return $result;
    }

    /**
     * @param  Collection<int, GameItemDefinition>  $pool
     * @param  callable(GameItemDefinition): array<string, mixed>  $buildListing
     * @return Collection<int, array<string, mixed>>
     */
    private function buildUniqueCategoryListings(
        Collection $pool,
        callable $buildListing,
        ?string $listingCategory = null,
    ): Collection {
        /** @var Collection<int, array<string, mixed>> $result */
        $result = new Collection;

        foreach ($pool->values() as $index => $definition) {
            /** @var GameItemDefinition $definition */
            $listing = $buildListing($definition);
            $category = $listingCategory ?? (string) ($listing['type'] ?? $definition->type);
            $listing['listing_id'] = $this->makeListingId($category, $definition->id, (int) $index);
            $result->push($listing);
        }

        return $result;
    }

    private function makeListingId(string $category, int $definitionId, int $index): string
    {
        return $category . ':' . $definitionId . ':' . $index;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveListingKey(array $item): string
    {
        $listingId = $item['listing_id'] ?? null;
        if (is_string($listingId) && $listingId !== '') {
            return $listingId;
        }

        return (string) ($item['id'] ?? '');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function shopItemsPerCategoryBounds(): array
    {
        $min = max(1, (int) config('game.shop.items_per_category_min', self::SHOP_ITEMS_PER_CATEGORY_MIN));
        $max = max(1, (int) config('game.shop.items_per_category_max', self::SHOP_ITEMS_PER_CATEGORY_MAX));

        if ($min > $max) {
            return [$max, $max];
        }

        return [$min, $max];
    }

    private function shopItemsPerCategoryMin(): int
    {
        return $this->shopItemsPerCategoryBounds()[0];
    }

    private function shopItemsPerCategoryMax(): int
    {
        return $this->shopItemsPerCategoryBounds()[1];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function limitItemsPerCategory(Collection $items): Collection
    {
        $perCategoryMax = $this->shopItemsPerCategoryMax();

        return $items
            ->groupBy(fn (array $item): string => (string) ($item['type'] ?? 'unknown'))
            ->flatMap(function (Collection $group) use ($perCategoryMax) {
                if (($group->first()['type'] ?? null) === 'potion') {
                    return $group
                        ->sortByDesc(fn (array $item): int => (int) ($item['buy_price'] ?? 0))
                        ->values();
                }

                return $group
                    ->sortByDesc(fn (array $item): int => (int) ($item['buy_price'] ?? 0))
                    ->take($perCategoryMax)
                    ->values();
            })
            ->values();
    }

    /**
     * 购买物品
     *
     * @return array{copper:int,total_price:int,quantity:int,item_name:string}
     */
    public function buyItem(
        GameCharacter $character,
        int $itemId,
        int $quantity = 1,
        ?string $idempotencyKey = null,
        ?string $listingId = null,
    ): array {
        $isIdempotentRequest = $idempotencyKey !== null && $idempotencyKey !== '';
        $lockSuffix = $listingId !== null && $listingId !== '' ? $listingId : (string) $itemId;

        if ($isIdempotentRequest) {
            return $this->executeWithIdempotency(
                characterId: $character->id,
                operation: 'buy',
                idempotencyKey: $idempotencyKey,
                callback: fn () => $this->performBuy($character, $itemId, $quantity, $listingId),
                idempotencyTtlSeconds: self::IDEMPOTENCY_CACHE_TTL_SECONDS,
            );
        }

        return $this->executeWithDistributedLock(
            lockKey: 'shop:lock:buy:' . $character->id . ':' . $lockSuffix,
            callback: fn () => $this->performBuy($character, $itemId, $quantity, $listingId),
            timeoutSeconds: self::SHOP_LOCK_TIMEOUT_SECONDS,
        );
    }

    /**
     * 执行购买逻辑
     *
     * @return array{copper:int,total_price:int,quantity:int,item_name:string}
     */
    private function performBuy(GameCharacter $character, int $itemId, int $quantity, ?string $listingId = null): array
    {
        $definition = GameItemDefinition::find($itemId);

        if (! $definition || ! $definition->is_active) {
            throw new \InvalidArgumentException('物品不存在或不可购买');
        }

        if ($character->level < $definition->required_level) {
            throw new \InvalidArgumentException("需要等级 {$definition->required_level}");
        }

        $isPotion = $definition->type === 'potion';
        $isGem = $definition->type === 'gem';
        $equippedValueFloorsByType = $isPotion ? [] : $this->buildEquippedValueFloorsByType($character);
        $cachedShopItem = $isPotion ? null : $this->findCachedShopItem($character, $itemId, $equippedValueFloorsByType, $listingId);
        if ($cachedShopItem !== null) {
            /** @var array<string, int|float> $randomStats */
            $randomStats = is_array($cachedShopItem['base_stats'] ?? null) ? $cachedShopItem['base_stats'] : [];
            $quality = is_string($cachedShopItem['quality'] ?? null) ? $cachedShopItem['quality'] : 'common';
            $unitPrice = (int) ($cachedShopItem['buy_price'] ?? 0);
        } elseif ($isPotion) {
            $randomStats = $this->itemCalculator->generateRandomStats($definition);
            $quality = 'common';
            $unitPrice = $this->itemCalculator->calculateBuyPrice($definition, $randomStats, $quality);
        } elseif ($isGem) {
            throw new \InvalidArgumentException('该宝石已售罄或不在商店中');
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

        return DB::transaction(function () use ($character, $definition, $randomStats, $quality, $totalPrice, $quantity, $itemId, $isPotion, $isGem, $cachedShopItem, $listingId) {
            // 检查背包空间
            if (! $this->itemCreationService->hasInventorySpace($character, $quantity, $isPotion || $isGem)) {
                throw new \InvalidArgumentException($isPotion || $isGem ? '背包已满' : '背包空间不足');
            }

            // 药品处理
            if ($isPotion) {
                $this->itemCreationService->addPotionToInventory($character, $definition, $quantity, $randomStats);
            } elseif ($isGem) {
                $this->itemCreationService->addGemToInventory($character, $definition, $quantity, $randomStats);
                $this->recordPurchasedItem($character, $this->resolvePurchasedListingKey($cachedShopItem, $itemId, $listingId));
            } else {
                // 装备类物品
                $this->itemCreationService->createEquipmentItems($character, $definition, $quantity, $randomStats, $quality);

                // 记录已购买的装备
                $this->recordPurchasedItem($character, $this->resolvePurchasedListingKey($cachedShopItem, $itemId, $listingId));
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
    private function findCachedShopItem(
        GameCharacter $character,
        int $definitionId,
        array $equippedValueFloorsByType,
        ?string $listingId = null,
    ): ?array {
        $cacheKey = $this->getShopCacheKey($character);
        $cached = cache()->get($cacheKey);

        if (! is_array($cached)) {
            return null;
        }

        foreach (['equipment', 'gems'] as $cacheSection) {
            $items = $cached[$cacheSection] ?? null;
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if ((int) ($item['id'] ?? 0) !== $definitionId) {
                    continue;
                }
                if ($listingId !== null && $listingId !== '' && $this->resolveListingKey($item) !== $listingId) {
                    continue;
                }

                if ($cacheSection === 'equipment') {
                    return $this->hydrateEquipmentListing($item, $equippedValueFloorsByType);
                }

                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $cachedShopItem
     */
    private function resolvePurchasedListingKey(?array $cachedShopItem, int $definitionId, ?string $listingId): string
    {
        if ($listingId !== null && $listingId !== '') {
            return $listingId;
        }

        if (is_array($cachedShopItem)) {
            return $this->resolveListingKey($cachedShopItem);
        }

        return (string) $definitionId;
    }

    private function getNextDailyRefreshTimestamp(int $fromTimestamp): int
    {
        $timezone = (string) config('app.timezone', 'UTC');

        return Carbon::createFromTimestamp($fromTimestamp, $timezone)
            ->startOfDay()
            ->addDay()
            ->getTimestamp();
    }

    private function getSecondsUntilNextDailyRefresh(int $fromTimestamp): int
    {
        return max(60, $this->getNextDailyRefreshTimestamp($fromTimestamp) - $fromTimestamp);
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

    private function resolveGemStatKey(GameItemDefinition $definition): string
    {
        $gemStats = $definition->gem_stats;
        if (! is_array($gemStats) || $gemStats === []) {
            return 'gem:' . $definition->id;
        }

        $statKey = array_key_first($gemStats);

        return is_string($statKey) ? $statKey : 'gem:' . $definition->id;
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
        $targetValue = $this->itemCalculator->resolveShopTargetValue($valueFloor);
        $stats = $this->itemCalculator->ensureStatsMeetValueFloor($definition, $stats, $quality, $targetValue);

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
        $targetValue = $this->itemCalculator->resolveShopTargetValue($valueFloor);
        $stats = $this->itemCalculator->ensureStatsMeetValueFloor($definition, $stats, $quality, $targetValue);
        $item['base_stats'] = GameItem::normalizeStatsPrecision($stats);
        $item['buy_price'] = $this->itemCalculator->calculateBuyPrice($definition, $stats, $quality);

        return $item;
    }
}
