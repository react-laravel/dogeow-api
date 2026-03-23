<?php

namespace App\Services\Game\Combat;

use App\Models\Game\GameMonsterDefinition;

/**
 * 战斗奖励计算器
 */
class CombatRewardCalculator
{
    /**
     * 计算本回合死亡怪物的经验与铜币奖励(已乘难度系数)
     *
     * @param  array<int, array<string, mixed>>  $monstersUpdated
     * @param  array<int, int>  $hpAtRoundStart
     * @return array{0: int, 1: int}
     */
    public function calculateRoundDeathRewards(
        array $monstersUpdated,
        array $hpAtRoundStart,
        array $difficulty
    ): array {
        $totalExperience = 0;
        $totalCopper = 0;
        $rewardMultiplier = $difficulty['reward'] ?? 1;

        foreach ($monstersUpdated as $i => $monster) {
            $before = $hpAtRoundStart[$i] ?? 0;
            $after = $monster['hp'] ?? 0;
            if ($before > 0 && $after <= 0) {
                $totalExperience += $monster['experience'] ?? 0;

                $copperGained = $this->calculateMonsterCopperLoot($monster);
                $totalCopper += $copperGained;
            }
        }

        return [
            (int) ($totalExperience * $rewardMultiplier),
            (int) ($totalCopper * $rewardMultiplier),
        ];
    }

    /**
     * 根据怪物定义计算铜币掉落
     */
    public function calculateMonsterCopperLoot(array $monster): int
    {
        $monsterId = $monster['id'] ?? null;
        if (! $monsterId) {
            return rand(1, 10);
        }

        $definition = GameMonsterDefinition::query()->find($monsterId);
        if (! $definition) {
            return rand(1, 10);
        }

        $dropTable = $definition->drop_table ?? [];
        $level = $monster['level'] ?? $definition->level;

        $copperConfig = config('game.copper_drop');
        if (! empty($dropTable['copper_chance'])) {
            $copperChance = $dropTable['copper_chance'];
            $copperBase = (int) ($dropTable['copper_base'] ?? $copperConfig['base']);
            $copperRange = (int) ($dropTable['copper_range'] ?? $copperConfig['range']);
        } else {
            $copperChance = $copperConfig['chance'];
            $copperBase = (int) $copperConfig['base'];
            $copperRange = (int) $copperConfig['range'];
        }

        if (! $this->rollChance($copperChance)) {
            return 0;
        }

        return random_int($copperBase, $copperBase + $copperRange);
    }

    /**
     * 概率判定
     */
    private function rollChance(float $chance): bool
    {
        return mt_rand() / mt_getrandmax() < $chance;
    }
}
