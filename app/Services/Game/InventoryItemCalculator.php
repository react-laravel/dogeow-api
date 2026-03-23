<?php

namespace App\Services\Game;

use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;

/**
 * 物品计算辅助类
 */
class InventoryItemCalculator
{
    /**
     * 计算物品售价
     */
    public function calculateSellPrice(GameItem $item): int
    {
        $definition = $item->definition;
        /** @var GameItemDefinition|null $definition */
        // calculateBuyPrice already includes quality multiplier, so don't multiply again
        $basePrice = $this->calculateBuyPrice($definition, $item->stats ?? [], $item->quality);
        $sellRatio = (float) config('game.shop.sell_ratio', 0.3);

        return (int) ($basePrice * $sellRatio);
    }

    /**
     * 计算购买价格
     *
     * @param  array<string,int|float>  $stats
     */
    public function calculateBuyPrice(?GameItemDefinition $item, array $stats = [], string $quality = 'common'): int
    {
        if (! $item) {
            return 0;
        }

        // 优先使用固定价格
        if ($item->buy_price > 0) {
            return $item->buy_price;
        }

        /** @var array<string, mixed>|null $baseStats */
        $baseStats = $item->base_stats;
        $basePrice = 0;
        if (is_array($baseStats)) {
            $basePrice = isset($baseStats['price']) && is_numeric($baseStats['price']) ? (int) $baseStats['price'] : 0;
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
        $qualityMultiplier = is_array($qualityMultiplierConfig) ? (isset($qualityMultiplierConfig[$quality]) && is_numeric($qualityMultiplierConfig[$quality]) ? (float) $qualityMultiplierConfig[$quality] : 1.0) : 1.0;

        // 基础价格(按类型)
        $typeBasePriceConfig = config('game.shop.type_base_price', []);
        $typeBasePrice = 20;
        if (is_array($typeBasePriceConfig)) {
            $typeKey = (string) $item->type;
            $typeBasePrice = isset($typeBasePriceConfig[$typeKey]) && is_numeric($typeBasePriceConfig[$typeKey]) ? (float) $typeBasePriceConfig[$typeKey] : 20;
        }

        // 属性价格计算
        $statPriceConfig = config('game.shop.stat_price', []);
        $statPriceConfig = is_array($statPriceConfig) ? $statPriceConfig : [];
        $statsPrice = 0.0;
        foreach ($stats as $stat => $value) {
            $statMultiplier = isset($statPriceConfig[$stat]) && is_numeric($statPriceConfig[$stat]) ? (float) $statPriceConfig[$stat] : 2.0;
            $valueNumeric = (float) $value;
            $statsPrice += $valueNumeric * $statMultiplier;
        }

        return (int) (round((($typeBasePrice + $statsPrice) * $levelMultiplier * $qualityMultiplier) * 100));
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
