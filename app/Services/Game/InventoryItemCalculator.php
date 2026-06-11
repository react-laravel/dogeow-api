<?php

namespace App\Services\Game;

use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;

/**
 * 物品定价统一入口（与背包/角色显示一致：铜币级属性计价）
 */
class InventoryItemCalculator
{
    /** 不参与计价的属性键 */
    private const PRICING_EXCLUDED_STATS = ['restore', 'price'];

    /** 装备卖出价为估价的 50% */
    private const EQUIPMENT_SELL_RATIO = 0.5;

    /** 商店购入价相对出售价的倍率（装备为收回 50% 折价，故买价≈卖价×2） */
    private const SHOP_BUY_TO_SELL_MULTIPLIER = 2;

    /**
     * 属性价格系数（每 1 点属性对应的基础铜币）
     */
    private const STAT_PRICES = [
        'attack' => 3,
        'defense' => 2,
        'max_hp' => 0.5,
        'max_mana' => 0.3,
        'crit_rate' => 500,
        'crit_damage' => 200,
    ];

    /**
     * 物品类型价格系数
     */
    private const TYPE_PRICE_MULTIPLIERS = [
        'weapon' => 1.2,
        'helmet' => 1.0,
        'armor' => 1.3,
        'gloves' => 0.8,
        'boots' => 0.8,
        'belt' => 0.7,
        'ring' => 1.5,
        'amulet' => 1.8,
        'potion' => 0.5,
        'gem' => 1.0,
    ];

    /**
     * 计算物品卖出价（背包/角色显示、背包出售、商店回收）
     */
    public function calculateSellPrice(GameItem $item): int
    {
        $definition = $item->definition;
        /** @var GameItemDefinition|null $definition */
        if (! $definition) {
            return 0;
        }

        return match ($definition->type) {
            'potion' => $this->calculatePotionSellPrice($item),
            'gem' => $this->calculateGemSellPrice($definition),
            default => $this->calculateEquipmentSellPrice($item),
        };
    }

    /**
     * 根据背包实例计算商店购入价（含词缀、宝石等完整属性）
     */
    public function calculateItemBuyPrice(GameItem $item): int
    {
        $definition = $item->definition;
        /** @var GameItemDefinition|null $definition */
        if (! $definition) {
            return 0;
        }

        $quality = $item->quality ?? 'common';
        $stats = $this->resolvePricingStats($item);

        return $this->calculateBuyPrice(
            $definition,
            $stats,
            $quality,
            (int) ($item->sockets ?? 0),
        );
    }

    /**
     * @return array<string, int|float>
     */
    private function resolvePricingStats(GameItem $item): array
    {
        $definition = $item->definition;
        if (! $definition instanceof GameItemDefinition) {
            return [];
        }

        if ($definition->type === 'gem') {
            $gemStats = $definition->gem_stats;

            return is_array($gemStats) ? $this->normalizePricingStats($gemStats) : [];
        }

        $totalStats = $item->getTotalStats();
        if ($totalStats !== []) {
            return $this->normalizePricingStats($totalStats);
        }

        $baseStats = $definition->base_stats;

        return is_array($baseStats) ? $this->normalizePricingStats($baseStats) : [];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, int|float>
     */
    private function normalizePricingStats(array $stats): array
    {
        $normalized = [];
        foreach ($stats as $stat => $value) {
            if (in_array($stat, self::PRICING_EXCLUDED_STATS, true) || ! is_numeric($value)) {
                continue;
            }
            $normalized[$stat] = (float) $value;
        }

        return $normalized;
    }

    /**
     * 计算商店购入价
     *
     * @param  array<string,int|float>  $stats
     */
    public function calculateBuyPrice(
        ?GameItemDefinition $item,
        array $stats = [],
        string $quality = 'common',
        int $sockets = 0,
    ): int {
        if (! $item) {
            return 0;
        }

        if ($item->buy_price > 0) {
            return $item->buy_price;
        }

        /** @var array<string, mixed>|null $baseStats */
        $baseStats = $item->base_stats;
        if (is_array($baseStats) && isset($baseStats['price']) && is_numeric($baseStats['price']) && (int) $baseStats['price'] > 0) {
            return (int) $baseStats['price'];
        }

        return match ($item->type) {
            'potion' => $this->calculatePotionBuyPrice($stats, $baseStats),
            'gem' => $this->calculateGemBuyPrice($item),
            default => max(1, $this->calculateEquipmentFullPrice($item, $stats, $quality, $sockets)),
        };
    }

    private function calculateEquipmentSellPrice(GameItem $item): int
    {
        $definition = $item->definition;
        if (! $definition instanceof GameItemDefinition) {
            return 0;
        }

        $fullPrice = $this->calculateEquipmentFullPrice(
            $definition,
            $this->resolvePricingStats($item),
            $item->quality ?? 'common',
            (int) ($item->sockets ?? 0),
        );

        return max(1, (int) ($fullPrice * self::EQUIPMENT_SELL_RATIO));
    }

    /**
     * @param  array<string, int|float>  $stats
     */
    private function calculateEquipmentFullPrice(
        GameItemDefinition $definition,
        array $stats,
        string $quality,
        int $sockets,
    ): int {
        if ($stats === [] && is_array($definition->base_stats)) {
            $stats = $this->normalizePricingStats($definition->base_stats);
        }

        $basePrice = 0;
        foreach ($stats as $stat => $value) {
            $pricePerPoint = self::STAT_PRICES[$stat] ?? 1;
            $basePrice += (int) ((float) $value * $pricePerPoint);
        }

        $qualityMultiplier = GameItem::QUALITY_MULTIPLIERS[$quality] ?? 1.0;
        $type = $definition->type ?? 'weapon';
        $typeMultiplier = self::TYPE_PRICE_MULTIPLIERS[$type] ?? 1.0;
        $requiredLevel = $definition->required_level ?? 1;
        $levelMultiplier = 1 + ($requiredLevel / 50);
        $socketBonus = $sockets * 10;

        return (int) (($basePrice * $qualityMultiplier * $typeMultiplier * $levelMultiplier) + $socketBonus);
    }

    private function calculatePotionSellPrice(GameItem $item): int
    {
        $stats = $this->normalizePricingStats($item->stats ?? []);
        if ($stats === [] && $item->definition instanceof GameItemDefinition) {
            $baseStats = $item->definition->base_stats;
            if (is_array($baseStats)) {
                $stats = $this->normalizePricingStats($baseStats);
            }
        }

        $hpRestore = (int) ($stats['max_hp'] ?? 0);
        $manaRestore = (int) ($stats['max_mana'] ?? 0);
        $price = (int) ($hpRestore * 0.3 + $manaRestore * 0.2);

        return max(1, $price);
    }

    /**
     * @param  array<string, int|float>  $stats
     * @param  array<string, mixed>|null  $definitionBaseStats
     */
    private function calculatePotionBuyPrice(array $stats, ?array $definitionBaseStats): int
    {
        $normalized = $this->normalizePricingStats($stats);
        if ($normalized === [] && is_array($definitionBaseStats)) {
            $normalized = $this->normalizePricingStats($definitionBaseStats);
        }

        $sellPrice = max(1, (int) (($normalized['max_hp'] ?? 0) * 0.3 + ($normalized['max_mana'] ?? 0) * 0.2));

        return max(1, $sellPrice * self::SHOP_BUY_TO_SELL_MULTIPLIER);
    }

    private function calculateGemSellPrice(GameItemDefinition $definition): int
    {
        return $this->calculateGemPriceFromStats($definition->gem_stats ?? []);
    }

    private function calculateGemBuyPrice(GameItemDefinition $definition): int
    {
        return max(1, $this->calculateGemSellPrice($definition) * self::SHOP_BUY_TO_SELL_MULTIPLIER);
    }

    /**
     * @param  array<string, mixed>  $gemStats
     */
    private function calculateGemPriceFromStats(array $gemStats): int
    {
        $price = 0;

        foreach ($gemStats as $stat => $value) {
            if (! is_numeric($value)) {
                continue;
            }
            $pricePerPoint = self::STAT_PRICES[$stat] ?? 1;
            $price += (int) ((float) $value * $pricePerPoint);
        }

        return max(1, $price);
    }

    /**
     * 获取药品效果
     *
     * @return array{hp:int, mana:int}
     */
    public function getPotionEffects(GameItem $item): array
    {
        $def = $item->definition;
        /** @var array<string, mixed>|null $rawStats */
        $rawStats = $def !== null ? $def->base_stats : null;
        /** @var array<string, mixed> $baseStats */
        $baseStats = $rawStats ?? [];

        $hp = 0;
        if (isset($baseStats['max_hp']) && is_numeric($baseStats['max_hp'])) {
            $hp = (int) $baseStats['max_hp'];
        } elseif (isset($baseStats['restore_amount']) && is_numeric($baseStats['restore_amount'])) {
            $hp = (int) $baseStats['restore_amount'];
        }

        $mana = 0;
        if (isset($baseStats['max_mana']) && is_numeric($baseStats['max_mana'])) {
            $mana = (int) $baseStats['max_mana'];
        }

        return [
            'hp' => $hp,
            'mana' => $mana,
        ];
    }

    /**
     * 格式化恢复消息
     *
     * @param  array<string,int>  $effects
     */
    public function formatRestoreMessage(array $effects): string
    {
        $restoreText = [];

        $hp = (int) ($effects['hp'] ?? 0);
        $mana = (int) ($effects['mana'] ?? 0);

        if ($hp > 0) {
            $restoreText[] = "{$hp} 点生命值";
        }
        if ($mana > 0) {
            $restoreText[] = "{$mana} 点法力值";
        }

        return implode('和', $restoreText);
    }

    /**
     * 生成随机属性
     *
     * @return array<string,int|float>
     */
    public function generateRandomStats(GameItemDefinition $definition): array
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
     * 生成随机品质
     */
    public function generateRandomQuality(int $requiredLevel): string
    {
        $rand = rand(1, 100);

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
}
