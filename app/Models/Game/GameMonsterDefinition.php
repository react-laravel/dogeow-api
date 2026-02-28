<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $drop_table
 */
class GameMonsterDefinition extends Model
{
    protected $fillable = [
        'name',
        'type',
        'level',
        'hp_base',
        'attack_base',
        'defense_base',
        'experience_base',
        'drop_table',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'drop_table' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public const TYPES = ['normal', 'elite', 'boss'];

    /**
     * 获取战斗日志
     */
    public function combatLogs(): HasMany
    {
        return $this->hasMany(GameCombatLog::class, 'monster_id');
    }

    /**
     * 获取生命值（直接返回数据库值）
     */
    public function getHp(): int
    {
        return (int) $this->hp_base;
    }

    /**
     * 获取攻击力（直接返回数据库值）
     */
    public function getAttack(): int
    {
        return (int) $this->attack_base;
    }

    /**
     * 获取防御力（直接返回数据库值）
     */
    public function getDefense(): int
    {
        return (int) $this->defense_base;
    }

    /**
     * 获取经验值（直接返回数据库值）
     */
    public function getExperience(): int
    {
        return (int) $this->experience_base;
    }

    /**
     * 获取完整战斗属性
     *
     * @return array{hp:int,attack:int,defense:int,experience:int}
     */
    public function getCombatStats(): array
    {
        return [
            'hp' => $this->getHp(),
            'attack' => $this->getAttack(),
            'defense' => $this->getDefense(),
            'experience' => $this->getExperience(),
        ];
    }

    /**
     * 生成掉落
     *
     * @param  int  $characterLevel  角色等级
     * @return array 掉落物品
     */
    public function generateLoot(int $characterLevel): array
    {
        $loot = [];
        $dropTable = $this->drop_table ?? [];

        // 药水掉落
        $potionDropChance = $dropTable['potion_chance'] ?? 0.1;
        if ($this->rollChance($potionDropChance)) {
            $potionType = $this->weightedRandom(['hp' => 0.6, 'mp' => 0.4]);
            $potionLevel = match (true) {
                $this->level <= 10 => 'minor',
                $this->level <= 30 => 'light',
                $this->level <= 60 => 'medium',
                default => 'full',
            };

            $loot['potion'] = [
                'type' => 'potion',
                'sub_type' => $potionType,
                'level' => $potionLevel,
            ];
        }

        // 装备掉落
        $dropChance = $dropTable['item_chance'] ?? 0.05;
        if ($this->rollChance($dropChance)) {
            $itemTypes = $dropTable['item_types'] ?? ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'ring', 'amulet', 'belt'];
            $itemType = $itemTypes[array_rand($itemTypes)];

            $quality = $this->generateItemQuality();

            $loot['item'] = [
                'type' => $itemType,
                'quality' => $quality,
                'level' => min($characterLevel, $this->level + 3),
            ];
        }

        return $loot;
    }

    /**
     * 生成物品品质
     */
    private function generateItemQuality(): string
    {
        $roll = mt_rand(1, 10000) / 100;
        $chances = config('game.item_quality_chances');

        // 测试模式概率加成
        if ($this->isTestMode()) {
            $multipliers = config('game.test_mode.quality_multiplier', []);
            foreach ($chances as $quality => $chance) {
                $multiplier = $multipliers[$quality] ?? 1;
                $chances[$quality] = $chance * $multiplier;
            }
        }

        $cumulative = 0;
        foreach ($chances as $quality => $chance) {
            $cumulative += $chance;
            if ($roll >= 100 - $cumulative) {
                return $quality;
            }
        }

        return 'common';
    }

    /**
     * 随机概率判断
     */
    private function rollChance(float $chance): bool
    {
        // 测试模式：掉落概率大幅提升
        if ($this->isTestMode()) {
            $chanceMultiplier = config('game.test_mode.copper_drop_chance', 10);
            $chance = min(1.0, $chance * $chanceMultiplier);
        }

        return mt_rand() / mt_getrandmax() < $chance;
    }

    /**
     * 判断是否启用测试模式
     */
    private function isTestMode(): bool
    {
        $testMode = config('game.test_mode.enabled', false);
        if ($testMode) {
            return true;
        }

        // 也支持 APP_ENV 为 testing 或 sandbox
        $env = app()->environment();

        return in_array($env, ['testing', 'sandbox', 'test']);
    }

    /**
     * 加权随机选择
     */
    private function weightedRandom(array $weights): string
    {
        $sum = array_sum($weights);
        if ($sum <= 0) {
            return array_key_first($weights);
        }
        $rand = mt_rand() / mt_getrandmax() * $sum;
        $cumulative = 0;
        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
