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

        // 仅药水使用定义表固定标价；装备按实例属性计价
        if ($item->buy_price > 0 && $item->type === 'potion') {
            return $item->buy_price;
        }

        /** @var array<string, mixed>|null $baseStats */
        $baseStats = $item->base_stats;
        if (is_array($baseStats) && isset($baseStats['price']) && is_numeric($baseStats['price']) && (int) $baseStats['price'] > 0) {
            return (int) $baseStats['price'];
        }

        return match ($item->type) {
            'potion' => $this->calculatePotionBuyPrice($stats, $baseStats),
            'gem' => $stats !== []
                ? $this->calculateGemBuyPriceFromStats($stats)
                : $this->calculateGemBuyPrice($item),
            default => max(1, $this->calculateEquipmentFullPrice($item, $stats, $quality, $sockets)),
        };
    }

    /**
     * 装备实例的估价（与商店购入价同一套属性公式，不含卖出折价）
     */
    public function calculateItemValue(GameItem $item): int
    {
        $definition = $item->definition;
        if (! $definition instanceof GameItemDefinition) {
            return 0;
        }

        if ($definition->type === 'potion' || $definition->type === 'gem') {
            return $this->calculateBuyPrice($definition, $this->resolvePricingStats($item), $item->quality ?? 'common');
        }

        return $this->calculateEquipmentValue(
            $definition,
            $this->resolvePricingStats($item),
            $item->quality ?? 'common',
            (int) ($item->sockets ?? 0),
        );
    }

    /**
     * @param  array<string, int|float>  $stats
     */
    public function calculateEquipmentValue(
        GameItemDefinition $definition,
        array $stats,
        string $quality = 'common',
        int $sockets = 0,
    ): int {
        return max(1, $this->calculateEquipmentFullPrice($definition, $stats, $quality, $sockets));
    }

    /**
     * 在已装备估价下限与上限倍率之间随机取目标估价（用于商店刷新，避免全部挤在下限）
     */
    public function resolveShopTargetValue(int $equippedValueFloor): int
    {
        if ($equippedValueFloor <= 0) {
            return 0;
        }

        $multiplier = (float) config('game.shop.value_ceiling_multiplier', 2.0);
        $multiplier = max(1.0, $multiplier);
        $ceiling = (int) ceil($equippedValueFloor * $multiplier);

        return random_int($equippedValueFloor, $ceiling);
    }

    /**
     * 将商店装备属性抬升到不低于指定估价（保证刷新物为升级而非降级）
     *
     * @param  array<string, int|float>  $stats
     * @return array<string, int|float>
     */
    public function ensureStatsMeetValueFloor(
        GameItemDefinition $definition,
        array $stats,
        string $quality,
        int $valueFloor,
        int $sockets = 0,
    ): array {
        $stats = $this->normalizePricingStats($stats);
        if ($stats === []) {
            return $stats;
        }

        $stats = $this->clampStatsToShopCeiling($definition, $stats, $quality);

        if ($valueFloor <= 0) {
            return $stats;
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $currentValue = $this->calculateEquipmentFullPrice($definition, $stats, $quality, $sockets);
            if ($currentValue >= $valueFloor) {
                return $stats;
            }

            if ($currentValue <= 0) {
                break;
            }

            $ratio = $valueFloor / $currentValue;
            $scaled = [];
            foreach ($stats as $stat => $value) {
                if (in_array($stat, ['crit_rate', 'crit_damage'], true)) {
                    $scaled[$stat] = round((float) $value * $ratio, 4);
                } else {
                    $scaled[$stat] = max(1, (int) ceil((float) $value * $ratio));
                }
            }
            $stats = $this->clampStatsToShopCeiling($definition, $scaled, $quality);
        }

        return $stats;
    }

    /**
     * 商店装备单条属性的合理上限（基于模板等级、定义基础值与随机上限）
     *
     * @return array<string, int|float>
     */
    public function resolveMaxShopStats(GameItemDefinition $definition, string $quality = 'common'): array
    {
        $level = max(1, (int) $definition->required_level);
        $qualityMultiplier = GameItem::QUALITY_MULTIPLIERS[$quality] ?? 1.0;
        $headroom = max(1.0, (float) config('game.shop.stat_ceiling_headroom', 1.25));
        $scale = $qualityMultiplier * $headroom;
        /** @var array<string, mixed> $baseStats */
        $baseStats = is_array($definition->base_stats) ? $definition->base_stats : [];

        $maxStats = match ($definition->type) {
            'weapon' => [
                'attack' => max((int) ($baseStats['attack'] ?? 0), 4 + (int) floor($level / 8)),
                'crit_rate' => 0.1,
                'crit_damage' => 0.5,
            ],
            'helmet', 'armor' => [
                'defense' => max((int) ($baseStats['defense'] ?? 0), 3 + (int) floor($level / 10)),
                'max_hp' => max((int) ($baseStats['max_hp'] ?? 0), 6 + (int) floor($level / 5)),
                'crit_rate' => 0.05,
            ],
            'gloves' => [
                'attack' => max((int) ($baseStats['attack'] ?? 0), 3 + (int) floor($level / 10)),
                'crit_rate' => 0.08,
            ],
            'boots' => [
                'defense' => max((int) ($baseStats['defense'] ?? 0), 2 + (int) floor($level / 12)),
                'max_hp' => max((int) ($baseStats['max_hp'] ?? 0), 4 + (int) floor($level / 6)),
                'dexterity' => max((int) ($baseStats['dexterity'] ?? 0), 3),
            ],
            'belt' => [
                'max_hp' => max((int) ($baseStats['max_hp'] ?? 0), 8 + (int) floor($level / 4)),
                'max_mana' => max((int) ($baseStats['max_mana'] ?? 0), 6 + (int) floor($level / 5)),
            ],
            'ring' => [
                'attack' => max((int) ($baseStats['attack'] ?? 0), 4 + (int) floor($level / 6)),
                'defense' => max((int) ($baseStats['defense'] ?? 0), 4 + (int) floor($level / 6)),
                'max_hp' => max((int) ($baseStats['max_hp'] ?? 0), 4 + (int) floor($level / 6)),
                'max_mana' => max((int) ($baseStats['max_mana'] ?? 0), 4 + (int) floor($level / 6)),
                'crit_rate' => 0.08,
                'strength' => max((int) ($baseStats['strength'] ?? 0), 4 + (int) floor($level / 6)),
                'dexterity' => max((int) ($baseStats['dexterity'] ?? 0), 4 + (int) floor($level / 6)),
                'energy' => max((int) ($baseStats['energy'] ?? 0), 4 + (int) floor($level / 6)),
            ],
            'amulet' => [
                'max_hp' => max((int) ($baseStats['max_hp'] ?? 0), 10 + (int) floor($level / 4)),
                'max_mana' => max((int) ($baseStats['max_mana'] ?? 0), 8 + (int) floor($level / 5)),
                'defense' => max((int) ($baseStats['defense'] ?? 0), 4),
            ],
            default => [],
        };

        $scaled = [];
        foreach ($maxStats as $stat => $value) {
            if (in_array($stat, ['crit_rate', 'crit_damage'], true)) {
                $scaled[$stat] = min(1.0, round((float) $value * $scale, 4));
            } else {
                $scaled[$stat] = (int) ceil((float) $value * $scale);
            }
        }

        return $scaled;
    }

    /**
     * @param  array<string, int|float>  $stats
     * @return array<string, int|float>
     */
    public function clampStatsToShopCeiling(
        GameItemDefinition $definition,
        array $stats,
        string $quality,
    ): array {
        $ceiling = $this->resolveMaxShopStats($definition, $quality);
        $clamped = [];

        foreach ($stats as $stat => $value) {
            if (! isset($ceiling[$stat])) {
                $clamped[$stat] = $value;

                continue;
            }

            if (in_array($stat, ['crit_rate', 'crit_damage'], true)) {
                $clamped[$stat] = min((float) $value, (float) $ceiling[$stat]);
            } else {
                $clamped[$stat] = min((float) $value, (float) $ceiling[$stat]);
            }
        }

        return $clamped;
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
            $pricePerPoint = $this->resolveStatPrice($stat);
            $basePrice += (int) ($this->statValueToPricePoints($stat, (float) $value) * $pricePerPoint);
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
     * @param  array<string, int|float>  $gemStats
     */
    public function calculateGemBuyPriceFromStats(array $gemStats): int
    {
        return max(1, $this->calculateGemPriceFromStats($gemStats) * self::SHOP_BUY_TO_SELL_MULTIPLIER);
    }

    /**
     * @param  array<string, int|float>  $gemStats
     */
    public function calculateGemSellPriceFromStats(array $gemStats): int
    {
        return $this->calculateGemPriceFromStats($gemStats);
    }

    /**
     * 按配置范围为商店宝石掷骰属性
     *
     * @return array<string, int|float>
     */
    public function rollGemStats(GameItemDefinition $definition): array
    {
        $template = $definition->gem_stats;
        if (! is_array($template) || $template === []) {
            return [];
        }

        $statKey = array_key_first($template);
        if (! is_string($statKey)) {
            return [];
        }

        /** @var array<string, array{0: int|float, 1: int|float}> $ranges */
        $ranges = config('game.shop.gem_stat_ranges', []);
        if (! isset($ranges[$statKey]) || ! is_array($ranges[$statKey]) || count($ranges[$statKey]) < 2) {
            $templateValue = $template[$statKey] ?? 0;
            if (! is_numeric($templateValue)) {
                return [];
            }

            return [$statKey => is_float($templateValue + 0) ? (float) $templateValue : (int) $templateValue];
        }

        [$min, $max] = $ranges[$statKey];
        if (! is_numeric($min) || ! is_numeric($max)) {
            return [];
        }

        $minValue = (float) $min;
        $maxValue = (float) $max;
        if ($minValue > $maxValue) {
            [$minValue, $maxValue] = [$maxValue, $minValue];
        }

        if (in_array($statKey, ['crit_rate', 'crit_damage'], true)) {
            $scale = 10000;
            $rolled = random_int((int) round($minValue * $scale), (int) round($maxValue * $scale)) / $scale;

            return [$statKey => $rolled];
        }

        return [$statKey => random_int((int) $minValue, (int) $maxValue)];
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
            $pricePerPoint = $this->resolveStatPrice($stat);
            $price += (int) ($this->statValueToPricePoints($stat, (float) $value) * $pricePerPoint);
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
                $stats['attack'] = rand(1, 4) + (int) floor($definition->required_level / 8);
                if (rand(1, 100) <= 30) {
                    $stats['crit_rate'] = (float) bcdiv((string) rand(1, 10), '100', 4);
                }
                if (rand(1, 100) <= 20) {
                    $stats['crit_damage'] = (float) bcdiv((string) rand(5, 20), '100', 4);
                }
                break;

            case 'helmet':
            case 'armor':
                $stats['defense'] = rand(1, 3) + (int) floor($definition->required_level / 10);
                $stats['max_hp'] = rand(2, 6) + (int) floor($definition->required_level / 5);
                if (rand(1, 100) <= 25) {
                    $stats['crit_rate'] = (float) bcdiv((string) rand(1, 5), '100', 4);
                }
                break;

            case 'gloves':
                $stats['attack'] = rand(1, 3) + (int) floor($definition->required_level / 10);
                $stats['crit_rate'] = (float) bcdiv((string) rand(2, 8), '100', 4);
                break;

            case 'boots':
                $stats['defense'] = rand(1, 2) + (int) floor($definition->required_level / 12);
                $stats['max_hp'] = rand(1, 4) + (int) floor($definition->required_level / 6);
                if (rand(1, 100) <= 30) {
                    $stats['dexterity'] = rand(1, 3);
                }
                break;

            case 'belt':
                $stats['max_hp'] = rand(3, 8) + (int) floor($definition->required_level / 4);
                $stats['max_mana'] = rand(2, 6) + (int) floor($definition->required_level / 5);
                break;

            case 'ring':
                $ringStats = ['attack', 'defense', 'max_hp', 'max_mana', 'crit_rate', 'strength', 'dexterity', 'energy'];
                $selectedStat = $ringStats[array_rand($ringStats)];
                if ($selectedStat === 'crit_rate') {
                    $stats[$selectedStat] = (float) bcdiv((string) rand(1, 8), '100', 4);
                } else {
                    $stats[$selectedStat] = rand(1, 4) + (int) floor($definition->required_level / 6);
                }
                if (rand(1, 100) <= 40) {
                    $secondStat = $ringStats[array_rand($ringStats)];
                    if ($secondStat === 'crit_rate') {
                        $stats[$secondStat] = (float) bcdiv((string) rand(1, 5), '100', 4);
                    } else {
                        $stats[$secondStat] = rand(1, 3) + (int) floor($definition->required_level / 8);
                    }
                }
                break;

            case 'amulet':
                $stats['max_hp'] = rand(4, 10) + (int) floor($definition->required_level / 4);
                $stats['max_mana'] = rand(3, 8) + (int) floor($definition->required_level / 5);
                if (rand(1, 100) <= 30) {
                    $stats['defense'] = rand(1, 4);
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

    private function resolveStatPrice(string $stat): float
    {
        $configured = config('game.shop.stat_price');
        if (is_array($configured) && isset($configured[$stat]) && is_numeric($configured[$stat])) {
            return (float) $configured[$stat];
        }

        return (float) (self::STAT_PRICES[$stat] ?? 1);
    }

    /**
     * 将暴击率/暴伤等小数属性换算为计价用的「百分点」
     */
    private function statValueToPricePoints(string $stat, float $value): float
    {
        if (in_array($stat, ['crit_rate', 'crit_damage'], true) && abs($value) <= 1.0) {
            return $value * 100;
        }

        return $value;
    }
}
