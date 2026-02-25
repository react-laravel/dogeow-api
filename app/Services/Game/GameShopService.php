<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use Illuminate\Support\Facades\Cache;

class GameShopService
{
    /** 商店装备列表缓存时间（秒） */
    private const SHOP_CACHE_TTL_SECONDS = 1800; // 30 分钟

    /** 强制刷新商店费用（铜币），1 银 = 100 铜 */
    public const REFRESH_COST_COPPER = 100;

    /**
     * 清除当前角色的商店装备缓存（强制下次取列表时重新生成）
     */
    public function clearShopCache(GameCharacter $character): void
    {
        Cache::forget("rpg:shop:{$character->id}");
    }

    /**
     * 强制刷新商店：扣除 1 银币后清除缓存并返回新列表
     */
    /**
     * @return array{items: \Illuminate\Support\Collection<int, array<string,mixed>>, player_copper: int, next_refresh_at: int}
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
     */
    /**
        * @return array{items: \Illuminate\Support\Collection<int, array<string,mixed>>, player_copper: int, next_refresh_at: int, purchased: array<int>}
     */
    public function getShopItems(GameCharacter $character): array
    {
        // 药品固定显示
        $fixedPotionItems = $this->buildFixedPotionItems($character);

        // 装备列表使用缓存
        $cacheKey = sprintf('game_shop_%s', $character->id);
        $cached = cache()->get($cacheKey, []);
        $equipmentArray = [];
        if (is_array($cached) && isset($cached['equipment']) && is_array($cached['equipment'])) {
            $equipmentArray = $cached['equipment'];
        }
        $equipment = collect($equipmentArray);

        // 获取已购买的装备ID列表
        $purchasedItemIds = $this->getPurchasedItemIds($character);

        if (is_array($cached) && isset($cached['equipment'], $cached['refreshed_at'])) {
            /** @var array<int, array<string,mixed>> $cachedEquipment */
            $cachedEquipment = is_array($cached['equipment']) ? $cached['equipment'] : [];
            $cachedRefreshed = isset($cached['refreshed_at']) && is_numeric($cached['refreshed_at']) ? (int) $cached['refreshed_at'] : time();
            $randomEquipmentItems = collect($cachedEquipment)
                ->filter(fn ($item) => is_array($item) && (($item['required_level'] ?? 0) <= $character->level))
                // 过滤掉已购买的物品
                ->filter(fn ($item) => is_array($item) && ! in_array($item['id'], $purchasedItemIds))
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

        $purchased = [];
        if (is_array($cached) && isset($cached['purchased']) && is_array($cached['purchased'])) {
            $purchased = array_map(static fn($v): int => (int) $v, $cached['purchased']);
        }

        $refreshedAt = time();
        if (is_array($cached) && isset($cached['refreshed_at']) && is_numeric($cached['refreshed_at'])) {
            $refreshedAt = (int) $cached['refreshed_at'];
        }

        return [
            'items' => $shopItems,
            'player_copper' => (int) $character->copper,
            'next_refresh_at' => $nextRefreshAt,
            'purchased' => $purchased,
        ];
    }

    /**
     * 获取已购买的物品ID列表（仅装备，排除药品）
        *
        * @return int[]
     */
    private function getPurchasedItemIds(GameCharacter $character): array
    {
        $cacheKey = "rpg:shop:purchased:{$character->id}";
        $purchased = Cache::get($cacheKey);

        if (! is_array($purchased)) {
            return [];
        }

        // 过滤掉过期记录（超过缓存时间）
        $shopCacheKey = "rpg:shop:{$character->id}";
        $shopCache = Cache::get($shopCacheKey);

        if (! is_array($shopCache) || ! isset($shopCache['refreshed_at'])) {
            return [];
        }

        $cacheAge = time() - (isset($shopCache['refreshed_at']) ? (int) $shopCache['refreshed_at'] : 0);
        if ($cacheAge > self::SHOP_CACHE_TTL_SECONDS) {
            // 缓存已过期，清空已购买记录
            $this->clearPurchasedItems($character);

            return [];
        }

        // Return normalized ints
        return array_values(array_map(static fn($v): int => (int) $v, $purchased));
    }

    /**
     * 记录已购买的物品ID（仅装备）
     */
    public function recordPurchasedItem(GameCharacter $character, int $definitionId): void
    {
        $cacheKey = "rpg:shop:purchased:{$character->id}";
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
        $cacheKey = "rpg:shop:purchased:{$character->id}";
        Cache::forget($cacheKey);
    }

    /**
     * 构建固定药品列表
     *
     * @return \Illuminate\Support\Collection<int, array{id:int,name:string,type:string,sub_type:string|null,base_stats:array<string,mixed>,required_level:int,icon:string|null,description:string|null,buy_price:int,sell_price:int}>
     */
    private function buildFixedPotionItems(GameCharacter $character): \Illuminate\Support\Collection
    {
        $potionDefinitions = GameItemDefinition::query()
            ->where('is_active', true)
            ->where('type', 'potion')
            ->where('required_level', '<=', $character->level)
            ->orderBy('sub_type')
            ->orderByDesc('required_level')
            ->get();

        $fixedPotions = $potionDefinitions->unique('sub_type')->values();

        return $fixedPotions->map(function ($definition) {
            $randomStats = $this->generateRandomStats($definition);
            $buyPrice = $this->calculateBuyPrice($definition, $randomStats);

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
    }

    /**
     * 构建随机装备列表（仅包含所需等级不超过角色等级的装备）
     */
    /**
     * @return \Illuminate\Support\Collection<int, array{id:int,name:string,type:string,sub_type:string|null,base_stats:array<string,mixed>,quality:string,required_level:int,icon:string|null,description:string|null,buy_price:int}>
     */
    private function buildRandomEquipmentItems(GameCharacter $character): \Illuminate\Support\Collection
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

        return $selectedEquipments->map(function ($definition) {
            $randomStats = $this->generateRandomStats($definition);
            $quality = $this->generateRandomQuality($definition->required_level);

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
                'buy_price' => $this->calculateBuyPrice($definition, $randomStats, $quality),
            ];
        });
    }

    /**
     * 生成随机品质
     */
    private function generateRandomQuality(int $requiredLevel): string
    {
        $rand = rand(1, 100);

        // 从配置读取品质概率
        $qualityConfig = (array) config('game.shop.quality_chance', []);

        $mythicCfg = is_array($qualityConfig['mythic'] ?? null) ? $qualityConfig['mythic'] : [];
        $mythicBase = isset($mythicCfg['base']) ? (int) $mythicCfg['base'] : 0;
        $mythicPerLevel = $mythicCfg['per_level'] ?? 0.2;
        $mythicMax = $mythicCfg['max'] ?? 21;
        $mythicChance = min($mythicMax, $mythicBase + $requiredLevel * $mythicPerLevel);

        $legendaryCfg = is_array($qualityConfig['legendary'] ?? null) ? $qualityConfig['legendary'] : [];
        $legendaryBase = isset($legendaryCfg['base']) ? (int) $legendaryCfg['base'] : 0;
        $legendaryPerLevel = $legendaryCfg['per_level'] ?? 0.5;
        $legendaryMax = $legendaryCfg['max'] ?? 26;
        $legendaryChance = min($legendaryMax, $legendaryBase + $requiredLevel * $legendaryPerLevel);

        $rareCfg = is_array($qualityConfig['rare'] ?? null) ? $qualityConfig['rare'] : [];
        $rareBase = $rareCfg['base'] ?? 15;
        $rareMax = $rareCfg['max'] ?? 41;
        $rareChance = min($rareMax, $rareBase + $requiredLevel * ($rareCfg['per_level'] ?? 0));

        $magicCfg = is_array($qualityConfig['magic'] ?? null) ? $qualityConfig['magic'] : [];
        $magicBase = $magicCfg['base'] ?? 30;
        $magicMax = $magicCfg['max'] ?? 71;
        $magicChance = min($magicMax, $magicBase + $requiredLevel * ($magicCfg['per_level'] ?? 0));

        if ($rand <= $mythicChance) {
            return 'mythic';
        } elseif ($rand <= $legendaryChance) {
            return 'legendary';
        } elseif ($rand <= $rareChance) {
            return 'rare';
        } elseif ($rand <= $magicChance) {
            return 'magic';
        }

        return 'common';
    }

    /**
     * 生成随机属性
     */
    /**
     * @return array<string,int|float>
     */
    private function generateRandomStats(GameItemDefinition $definition): array
    {
        $stats = [];
        $type = $definition->type;

        switch ($type) {
            case 'weapon':
                $stats['attack'] = rand(5, 15) + $definition->required_level * 2;
                if (rand(1, 100) <= 30) {
                    $stats['crit_rate'] = (float) bcdiv((string) rand(1, 10), '100', 4);
                }
                if (rand(1, 100) <= 20) {
                    $stats['crit_damage'] = rand(20, 50);
                }
                break;

            case 'helmet':
            case 'armor':
                $stats['defense'] = rand(3, 10) + $definition->required_level;
                $stats['max_hp'] = rand(10, 30) + $definition->required_level * 5;
                if (rand(1, 100) <= 25) {
                    $stats['crit_rate'] = (float) bcdiv((string) rand(1, 5), '100', 4);
                }
                break;

            case 'gloves':
                $stats['attack'] = rand(2, 6) + $definition->required_level;
                $stats['crit_rate'] = (float) bcdiv((string) rand(2, 8), '100', 4);
                break;

            case 'boots':
                $stats['defense'] = rand(1, 5) + $definition->required_level;
                $stats['max_hp'] = rand(5, 20) + $definition->required_level * 3;
                if (rand(1, 100) <= 30) {
                    $stats['dexterity'] = rand(1, 3);
                }
                break;

            case 'belt':
                $stats['max_hp'] = rand(15, 40) + $definition->required_level * 4;
                $stats['max_mana'] = rand(10, 30) + $definition->required_level * 3;
                break;

            case 'ring':
                $ringStats = ['attack', 'defense', 'max_hp', 'max_mana', 'crit_rate', 'strength', 'dexterity', 'energy'];
                $selectedStat = $ringStats[array_rand($ringStats)];
                if ($selectedStat === 'crit_rate') {
                    $stats[$selectedStat] = (float) bcdiv((string) rand(1, 8), '100', 4);
                } else {
                    $stats[$selectedStat] = rand(3, 12) + $definition->required_level * 2;
                }
                if (rand(1, 100) <= 40) {
                    $secondStat = $ringStats[array_rand($ringStats)];
                    if ($secondStat === 'crit_rate') {
                        $stats[$secondStat] = (float) bcdiv((string) rand(1, 5), '100', 4);
                    } else {
                        $stats[$secondStat] = rand(2, 8) + $definition->required_level;
                    }
                }
                break;

            case 'amulet':
                $stats['max_hp'] = rand(20, 50) + $definition->required_level * 5;
                $stats['max_mana'] = rand(15, 40) + $definition->required_level * 4;
                if (rand(1, 100) <= 30) {
                    $stats['defense'] = rand(5, 15);
                }
                break;

            case 'potion':
                $potionTypes = ['hp', 'mp'];
                $potionType = $potionTypes[array_rand($potionTypes)];
                $restoreAmount = rand(30, 100) + $definition->required_level * 10;
                $stats[$potionType === 'hp' ? 'max_hp' : 'max_mana'] = $restoreAmount;
                $stats['restore'] = $restoreAmount;
                break;
        }

        return $stats;
    }

    /**
     * 计算购买价格
        *
        * @param array<string,int|float> $stats
     */
    private function calculateBuyPrice(GameItemDefinition $item, array $stats = [], string $quality = 'common'): int
    {
        // 优先使用固定价格
        if ($item->buy_price > 0) {
            return $item->buy_price;
        }

        // base_stats may be array or null; guard access
        $basePrice = 0;
        if (is_array($item->base_stats)) {
            $basePrice = isset($item->base_stats['price']) && is_numeric($item->base_stats['price']) ? (int) $item->base_stats['price'] : 0;
        }

        if ($basePrice > 0) {
            return $basePrice;
        }

        // 从配置读取
        $levelMultiplierConfig = config('game.shop.level_price_multiplier', 0.5);
        $levelMultiplierConfig = is_numeric($levelMultiplierConfig) ? (float) $levelMultiplierConfig : 0.5;
        $levelMultiplier = 1 + ((int) $item->required_level * $levelMultiplierConfig);

        // 品质价格乘数
        $qualityMultiplierConfig = config('game.shop.quality_price_multiplier', []);
        $qualityMultiplier = is_array($qualityMultiplierConfig) ? (isset($qualityMultiplierConfig[$quality]) && is_numeric($qualityMultiplierConfig[$quality]) ? (float)$qualityMultiplierConfig[$quality] : 1.0) : 1.0;

        // 基础价格（按类型）
        $typeBasePriceConfig = config('game.shop.type_base_price', []);
        $typeBasePrice = 20;
        if (is_array($typeBasePriceConfig)) {
            $typeKey = (string) $item->type;
            $typeBasePrice = isset($typeBasePriceConfig[$typeKey]) && is_numeric($typeBasePriceConfig[$typeKey]) ? (float)$typeBasePriceConfig[$typeKey] : 20;
        }

        // 属性价格计算
        $statPriceConfig = config('game.shop.stat_price', []);
        $statPriceConfig = is_array($statPriceConfig) ? $statPriceConfig : [];
        $statsPrice = 0.0;
        foreach ($stats as $stat => $value) {
            $statMultiplier = isset($statPriceConfig[$stat]) && is_numeric($statPriceConfig[$stat]) ? (float) $statPriceConfig[$stat] : 2.0;
            $valueNumeric = is_numeric($value) ? (float) $value : 0.0;
            $statsPrice += $valueNumeric * $statMultiplier;
        }

        return (int) (round((($typeBasePrice + $statsPrice) * $levelMultiplier * $qualityMultiplier) * 100));
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
        $randomStats = $this->generateRandomStats($definition);

        // 计算总价
        $totalPrice = $this->calculateBuyPrice($definition, $randomStats) * $quantity;

        if ($character->copper < $totalPrice) {
            throw new \InvalidArgumentException('货币不足');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($character, $definition, $randomStats, $totalPrice, $quantity, $itemId) {
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

                    // 创建临时物品用于计算价格
                    $tempItem = new GameItem([
                        'character_id' => $character->id,
                        'definition_id' => $definition->id,
                        'quality' => 'common',
                        'stats' => $randomStats,
                        'affixes' => [],
                        'is_in_storage' => false,
                        'quantity' => $quantity,
                    ]);
                    $sellPrice = $tempItem->calculateSellPrice();

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

                // 创建临时物品用于计算价格
                $tempItem = new GameItem([
                    'character_id' => $character->id,
                    'definition_id' => $definition->id,
                    'quality' => 'common',
                    'stats' => $randomStats,
                    'affixes' => [],
                    'is_in_storage' => false,
                    'quantity' => 1,
                ]);
                $sellPrice = $tempItem->calculateSellPrice();

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

                // 记录已购买的装备，药品不记录
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
        $sellPrice = $this->calculateItemSellPrice($item) * $quantity;

        return \Illuminate\Support\Facades\DB::transaction(function () use ($character, $item, $quantity, $sellPrice) {
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

    /**
     * 计算物品出售价格
     */
    private function calculateItemSellPrice(GameItem $item): int
    {
        $buyPrice = $this->calculateBuyPrice($item->definition);
        $qualityMultiplier = GameItem::QUALITY_MULTIPLIERS[$item->quality] ?? 1.0;
        $sellRatio = (float) config('game.shop.sell_ratio', 0.3);

        return (int) ($buyPrice * $qualityMultiplier * $sellRatio);
    }
}
