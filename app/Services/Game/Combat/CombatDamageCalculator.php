<?php

namespace App\Services\Game\Combat;

use Illuminate\Support\Facades\Log;

/**
 * 战斗伤害计算器
 */
class CombatDamageCalculator
{
    /**
     * 对目标怪物施加角色伤害，返回更新后的怪物列表与总伤害
     *
     * @param  array<int, array<string, mixed>>  $monsters
     * @param  array<int, array<string, mixed>>  $targetMonsters
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    public function applyCharacterDamageToMonsters(
        array $monsters,
        array $targetMonsters,
        int $charAttack,
        int $skillDamage,
        bool $isCrit,
        float $charCritDamage,
        bool $useAoe
    ): array {
        $totalDamageDealt = 0;
        $monstersUpdated = [];

        foreach ($monsters as $idx => $m) {
            $m['damage_taken'] = -1;
            $m['was_attacked'] = false;

            // 新出现的怪物不受攻击
            if (isset($m['is_new']) && $m['is_new'] === true) {
                Log::info('Skipping new monster attack', ['monster' => $m['name'], 'is_new' => true]);
                $monstersUpdated[$idx] = $m;

                continue;
            }

            if (($m['hp'] ?? 0) <= 0) {
                $monstersUpdated[$idx] = $m;

                continue;
            }

            $isTarget = $this->isMonsterInTargets($m, $targetMonsters);
            if (! $isTarget) {
                $monstersUpdated[$idx] = $m;

                continue;
            }

            $mDefense = (int) ($m['defense'] ?? 0);
            $defenseReduction = config('game.combat.defense_reduction', 0.5);
            $baseDamage = $charAttack - $mDefense * $defenseReduction;
            $damage = $skillDamage > 0
                ? (int) ($baseDamage + $skillDamage)
                : (int) ($baseDamage * ($isCrit ? $charCritDamage : 1));
            $aoeMultiplier = config('game.combat.aoe_damage_multiplier', 0.7);
            $targetDamage = $useAoe ? (int) ($damage * $aoeMultiplier) : $damage;

            $m['hp'] = max(0, $m['hp'] - $targetDamage);
            $m['damage_taken'] = $targetDamage;
            $m['was_attacked'] = true;
            $totalDamageDealt += $targetDamage;
            $monstersUpdated[$idx] = $m;
        }

        // 清除所有新怪物标记
        foreach ($monstersUpdated as $idx => $m) {
            if (isset($m['is_new'])) {
                unset($monstersUpdated[$idx]['is_new']);
            }
        }

        return [$monstersUpdated, $totalDamageDealt];
    }

    /**
     * 计算基础攻击伤害与暴击额外伤害
     *
     * @param  array<int, array<string, mixed>>  $targetMonsters
     * @return array{0: int, 1: int}
     */
    public function computeBaseAttackDamage(
        array $targetMonsters,
        int $skillDamage,
        int $charAttack,
        float $charCritDamage,
        bool $isCrit,
        float $defenseReduction
    ): array {
        if (empty($targetMonsters)) {
            return [0, 0];
        }

        if ($skillDamage > 0) {
            return [$skillDamage, 0];
        }

        $firstTarget = reset($targetMonsters);
        $targetDefense = $firstTarget['defense'] ?? 0;
        $baseAttackDamage = max(0, (int) ($charAttack - $targetDefense * $defenseReduction));

        if (! $isCrit) {
            return [$baseAttackDamage, 0];
        }

        $critDamageAmount = (int) ($baseAttackDamage * ($charCritDamage - 1));
        $baseAttackDamage = (int) ($baseAttackDamage * $charCritDamage);

        return [$baseAttackDamage, $critDamageAmount];
    }

    /**
     * 计算所有存活怪物对角色造成的总反击伤害
     *
     * @param  array<int, array<string, mixed>>  $monstersUpdated
     */
    public function calculateMonsterCounterDamage(array $monstersUpdated, int $charDefense): int
    {
        $total = 0;
        foreach ($monstersUpdated as $m) {
            if (($m['hp'] ?? 0) <= 0) {
                continue;
            }
            $monsterAttack = $m['attack'] ?? 0;
            $monsterDefenseReduction = config('game.combat.monster_defense_reduction', 0.3);
            $monsterDamage = $monsterAttack - $charDefense * $monsterDefenseReduction;
            if ($monsterDamage > 0) {
                $total += (int) $monsterDamage;
            }
        }

        return $total;
    }

    /**
     * 按槽位判断是否为攻击目标
     *
     * @param  array<string, mixed>  $monster
     * @param  array<int, array<string, mixed>>  $targets
     */
    public function isMonsterInTargets(array $monster, array $targets): bool
    {
        $slot = $monster['position'] ?? null;
        if ($slot === null) {
            return false;
        }
        foreach ($targets as $tm) {
            if (($tm['position'] ?? null) === $slot) {
                return true;
            }
        }

        return false;
    }

    /**
     * 选择本回合攻击目标
     *
     * @param  array<int, array<string, mixed>>  $monsters
     * @return array<int, array<string, mixed>>
     */
    public function selectRoundTargets(array $monsters, bool $isAoeSkill): array
    {
        $aliveMonsters = array_filter($monsters, fn ($m) => ($m['hp'] ?? 0) > 0);
        if (empty($aliveMonsters)) {
            return [];
        }
        $aliveValues = array_values($aliveMonsters);
        if ($isAoeSkill) {
            return $aliveValues;
        }
        $randomIndex = array_rand($aliveValues);

        return [$aliveValues[$randomIndex]];
    }

    /**
     * 收集技能命中的目标位置
     *
     * @param  array<int, array<string, mixed>>  $targetMonsters
     * @return array<int, int>
     */
    public function getSkillTargetPositions(array $targetMonsters): array
    {
        $positions = array_map(fn ($m) => $m['position'] ?? null, $targetMonsters);

        return array_values(array_filter($positions, fn ($p) => $p !== null));
    }

    /**
     * 概率判定
     */
    public function rollChanceForProcessor(float $chance): bool
    {
        // $chance 是 0~1，例如 0.12 就是 12%概率
        return mt_rand() / mt_getrandmax() < $chance;
    }
}
