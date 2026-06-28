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

    private function resolveStatPrice(string $stat): float
    {
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
