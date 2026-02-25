<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Support\Facades\Log;

/**
 * 单回合战斗处理器：技能选择、目标选择、伤害计算、反击、奖励结算
 */
class CombatRoundProcessor
{
    /**
     * 处理一回合战斗（支持多怪物）
     *
     * @return array{round_damage_dealt: int, round_damage_taken: int, new_monster_hp: int, new_char_hp: int, new_char_mana: int, defeat: bool, has_alive_monster: bool, skills_used_this_round: array, new_cooldowns: array, new_skills_aggregated: array, monsters_updated: array, slots_where_monster_died_this_round: array<int>, experience_gained: int, copper_gained: int, round_details: array}
     */
    public function processOneRound(
        GameCharacter $character,
        int $currentRound,
        array $skillCooldowns,
        array $skillsUsedAggregated,
        array $requestedSkillIds = []
    ): array {
        $character->initializeHpMana();

        $charStats = $character->getCombatStats();
        $charHp = $character->getCurrentHp();
        $currentMana = $character->getCurrentMana();
        $charAttack = $charStats['attack'];
        $charDefense = $charStats['defense'];
        $charCritRate = $charStats['crit_rate'];
        $charCritDamage = $charStats['crit_damage'];

        $monsters = $character->combat_monsters ?? [];
        $difficulty = $character->getDifficultyMultipliers();

        // 统计本回合开始时的怪物信息
        $aliveMonstersAtStart = $this->getAliveMonsters($monsters);
        $monstersKilledThisRound = 0;
        $hpAtRoundStart = $this->getMonsterHpSnapshot($monsters);

        $skillResult = $this->resolveRoundSkill(
            $character,
            $requestedSkillIds,
            $currentRound,
            $currentMana,
            $skillCooldowns
        );
        $currentMana = $skillResult['mana'];
        $isAoeSkill = $skillResult['is_aoe'];
        $skillDamage = $skillResult['skill_damage'];
        $skillsUsedThisRound = $skillResult['skills_used_this_round'];
        $newCooldowns = $skillResult['new_cooldowns'];

        $isCrit = (rand(1, 100) / 100) <= $charCritRate;
        $targetMonsters = $this->selectRoundTargets($monsters, $isAoeSkill);
        $useAoe = $isAoeSkill && ! empty($targetMonsters);

        // 收集技能命中的目标位置
        $skillTargetPositions = $this->getSkillTargetPositions($targetMonsters);

        // 伤害构成详情
        $baseAttackDamage = 0;
        $critDamageAmount = 0;
        $aoeDamageAmount = 0;

        // 计算基础攻击伤害（用于日志）
        $defenseReduction = config('game.combat.defense_reduction', 0.5);
        [$baseAttackDamage, $critDamageAmount] = $this->computeBaseAttackDamage(
            $targetMonsters,
            $skillDamage,
            $charAttack,
            $charCritDamage,
            $isCrit,
            $defenseReduction
        );

        // AOE 伤害计算（用于日志）
        if ($useAoe) {
            $aoeMultiplier = config('game.combat.aoe_damage_multiplier', 0.7);
            $targetCount = count($targetMonsters);
            if ($targetCount > 1) {
                $aoeDamageAmount = (int) ($baseAttackDamage * (1 - $aoeMultiplier) * $targetCount);
            }
        }

        [$monstersUpdated, $totalDamageDealt] = $this->applyCharacterDamageToMonsters(
            $monsters,
            $targetMonsters,
            $charAttack,
            $skillDamage,
            $isCrit,
            $charCritDamage,
            $useAoe
        );

        // 统计本回合杀死的怪物数量，并记录死亡槽位（本回合不在此槽位生成新怪，避免与死亡动画重叠）
        $slotsWhereMonsterDiedThisRound = [];
        foreach ($monstersUpdated as $idx => $m) {
            if (($hpAtRoundStart[$idx] ?? 0) > 0 && ($m['hp'] ?? 0) <= 0) {
                $monstersKilledThisRound++;
                $slotsWhereMonsterDiedThisRound[] = $idx;
            }
        }

        $totalMonsterDamage = $this->calculateMonsterCounterDamage($monstersUpdated, $charDefense);
        $charHp -= $totalMonsterDamage;

        $character->combat_monsters = $monstersUpdated;
        $newTotalHp = array_sum(array_column($monstersUpdated, 'hp'));

        $newSkillsAggregated = $this->aggregateSkillsUsed($skillsUsedThisRound, $skillsUsedAggregated);
        $hasAliveMonster = $this->hasAliveMonster($monstersUpdated);

        [$totalExperience, $totalCopper] = $this->calculateRoundDeathRewards(
            $monstersUpdated,
            $hpAtRoundStart,
            $difficulty
        );

        // 获取第一个存活怪物的详细信息
        $firstAliveMonster = $this->getFirstAliveMonster($monstersUpdated);
        $roundDetails = $this->buildRoundDetails(
            $character,
            $firstAliveMonster,
            $charAttack,
            $charDefense,
            $charCritRate,
            $charCritDamage,
            $baseAttackDamage,
            $skillDamage,
            $critDamageAmount,
            $aoeDamageAmount,
            $totalDamageDealt,
            $defenseReduction,
            $totalMonsterDamage,
            $currentRound,
            count($aliveMonstersAtStart),
            $monstersKilledThisRound,
            $isCrit,
            $useAoe,
            $difficulty
        );

        return [
            'round_damage_dealt' => $totalDamageDealt,
            'round_damage_taken' => $totalMonsterDamage,
            'new_monster_hp' => $newTotalHp,
            'new_char_hp' => $charHp,
            'new_char_mana' => $currentMana,
            'defeat' => $charHp <= 0,
            'has_alive_monster' => $hasAliveMonster,
            'skills_used_this_round' => $skillsUsedThisRound,
            'skill_target_positions' => array_values($skillTargetPositions),
            'new_cooldowns' => $newCooldowns,
            'new_skills_aggregated' => $newSkillsAggregated,
            'monsters_updated' => $monstersUpdated,
            'slots_where_monster_died_this_round' => $slotsWhereMonsterDiedThisRound,
            'experience_gained' => $totalExperience,
            'copper_gained' => $totalCopper,
            'round_details' => $roundDetails,
        ];
    }

    /**
     * 解析本回合使用的技能（蓝量、冷却、单体/群体）
     * 智能选择：根据怪物血量和数量、技能伤害和消耗来决定使用最佳技能
     *
     * @return array{mana: int, is_aoe: bool, skill_damage: int, skills_used_this_round: array, new_cooldowns: array}
     */
    private function resolveRoundSkill(
        GameCharacter $character,
        array $requestedSkillIds,
        int $currentRound,
        int $currentMana,
        array $skillCooldowns
    ): array {
        $isAoeSkill = false;
        $skillDamage = 0;
        $skillsUsedThisRound = [];
        $newCooldowns = $skillCooldowns;

        $activeSkills = $character->skills()
            ->whereHas('skill', fn ($q) => $q->where('type', 'active'))
            ->with('skill')
            ->get();

        // 若前端指定了自动施法技能列表，只从该列表中选技能（否则会从全部主动技能里智能选择）
        if ($requestedSkillIds !== []) {
            $allowedIds = array_flip($requestedSkillIds);
            $activeSkills = $activeSkills->filter(fn ($cs) => $cs->skill && isset($allowedIds[$cs->skill->id]));
        }

        // 获取当前怪物信息用于智能选择
        $monsters = $character->combat_monsters ?? [];
        $aliveMonsters = array_filter($monsters, fn ($m) => ($m['hp'] ?? 0) > 0);
        $aliveMonsterCount = count($aliveMonsters);
        $lowHpMonsters = array_filter($aliveMonsters, fn ($m) => $m['hp'] > 0 && $m['hp'] <= ($m['max_hp'] ?? 100) * 0.3);
        $lowHpMonsterCount = count($lowHpMonsters);
        $totalMonsterHp = array_sum(array_column($aliveMonsters, 'hp'));

        // 角色基础攻击力，用于估算普通攻击伤害
        $charStats = $character->getCombatStats();
        $charAttack = $charStats['attack'];

        // 过滤出可用的技能（魔法值足够且不在冷却中）
        $availableSkills = [];
        foreach ($activeSkills as $charSkill) {
            $skill = $charSkill->skill;
            $cooldownEnd = $newCooldowns[$skill->id] ?? 0;

            if ($currentMana >= $skill->mana_cost && $cooldownEnd <= $currentRound) {
                $availableSkills[] = [
                    'char_skill' => $charSkill,
                    'skill' => $skill,
                    'damage' => (int) $skill->damage,
                    'mana_cost' => (int) $skill->mana_cost,
                    'cooldown' => (int) $skill->cooldown,
                    'is_aoe' => ($skill->target_type ?? 'single') === 'all',
                ];
            }
        }

        if (empty($availableSkills)) {
            // 没有可用技能，使用普通攻击
            return [
                'mana' => $currentMana,
                'is_aoe' => false,
                'skill_damage' => 0,
                'skills_used_this_round' => [],
                'new_cooldowns' => $newCooldowns,
            ];
        }

        // 智能选择最佳技能
        $selectedSkill = $this->selectOptimalSkill(
            $availableSkills,
            $aliveMonsterCount,
            $lowHpMonsterCount,
            $totalMonsterHp,
            $charAttack
        );

        if ($selectedSkill !== null) {
            $skill = $selectedSkill['skill'];
            $skillDamage = $selectedSkill['damage'];
            $currentMana -= $selectedSkill['mana_cost'];
            $newCooldowns[$skill->id] = $currentRound + $selectedSkill['cooldown'];
            $isAoeSkill = $selectedSkill['is_aoe'];
            $skillsUsedThisRound[] = [
                'skill_id' => $skill->id,
                'name' => $skill->name,
                'icon' => $skill->icon,
                'effect_key' => $skill->effect_key ?? null,
                'target_type' => $skill->target_type ?? 'single',
            ];

            return [
                'mana' => $currentMana,
                'is_aoe' => $isAoeSkill,
                'skill_damage' => $skillDamage,
                'skills_used_this_round' => $skillsUsedThisRound,
                'new_cooldowns' => $newCooldowns,
            ];
        }

        // 没有找到合适的技能，使用普通攻击
        return [
            'mana' => $currentMana,
            'is_aoe' => false,
            'skill_damage' => 0,
            'skills_used_this_round' => [],
            'new_cooldowns' => $newCooldowns,
        ];
    }

    /**
     * 智能选择最佳技能
     *
     * 选择策略：
     * 1. 如果所有怪物血量都很低，优先使用低消耗技能
     * 2. 如果有多只低血量怪物，优先使用群体技能
     * 3. 如果只有一只怪物且血量低，用单体技能
     * 4. 优先选择伤害足够击杀怪物的技能中消耗最低的
     * 5. 考虑技能效率（伤害/魔法消耗）
     *
     * @param  array  $availableSkills  可用技能列表
     * @param  int  $aliveMonsterCount  存活怪物数量
     * @param  int  $lowHpMonsterCount  低血量怪物数量
     * @param  int  $totalMonsterHp  怪物总血量
     * @param  int  $charAttack  角色攻击力
     * @return array|null 选中的技能或null（使用普通攻击）
     */
    private function selectOptimalSkill(
        array $availableSkills,
        int $aliveMonsterCount,
        int $lowHpMonsterCount,
        int $totalMonsterHp,
        int $charAttack
    ): ?array {
        if (empty($availableSkills)) {
            return null;
        }

        // 只有一个技能，直接使用
        if (count($availableSkills) === 1) {
            return $availableSkills[0];
        }

        // 计算普通攻击伤害
        $baseAttackDamage = (int) ($charAttack * 0.5);

        // 策略1：怪物数量 >= 3 且有多只低血量怪物，优先使用群体技能
        if ($aliveMonsterCount >= 3 && $lowHpMonsterCount >= 2) {
            $aoeSkills = array_filter($availableSkills, fn ($s) => $s['is_aoe']);
            if (! empty($aoeSkills)) {
                // 从群体技能中选择伤害最高且消耗可接受的
                usort($aoeSkills, function ($a, $b) {
                    // 优先按效率排序（伤害/消耗），其次按伤害排序
                    $efficiencyA = $a['mana_cost'] > 0 ? $a['damage'] / $a['mana_cost'] : $a['damage'];
                    $efficiencyB = $b['mana_cost'] > 0 ? $b['damage'] / $b['mana_cost'] : $b['damage'];
                    if (abs($efficiencyA - $efficiencyB) > 0.1) {
                        return $efficiencyB <=> $efficiencyA;
                    }

                    return $b['damage'] <=> $a['damage'];
                });

                return $aoeSkills[0];
            }
        }

        // 策略2：所有怪物血量都很低（总血量 <= 角色攻击力 * 2），优先使用低消耗技能
        if ($totalMonsterHp <= $charAttack * 2) {
            // 按魔法消耗排序，选择最经济的技能
            usort($availableSkills, function ($a, $b) {
                // 优先选择不需要魔法的技能
                if ($a['mana_cost'] === 0 && $b['mana_cost'] > 0) {
                    return -1;
                }
                if ($b['mana_cost'] === 0 && $a['mana_cost'] > 0) {
                    return 1;
                }
                // 然后按效率排序
                $efficiencyA = $a['mana_cost'] > 0 ? $a['damage'] / $a['mana_cost'] : $a['damage'] * 10;
                $efficiencyB = $b['mana_cost'] > 0 ? $b['damage'] / $b['mana_cost'] : $b['damage'] * 10;

                return $efficiencyB <=> $efficiencyA;
            });

            return $availableSkills[0];
        }

        // 策略3：正常战斗情况，选择伤害最高的技能（如果伤害 > 0）
        // 但要避免使用高消耗低效率的技能
        $skillsWithDamage = array_filter($availableSkills, fn ($s) => $s['damage'] > 0);
        if (! empty($skillsWithDamage)) {
            // 按伤害/消耗效率排序
            usort($skillsWithDamage, function ($a, $b) {
                $efficiencyA = $a['mana_cost'] > 0 ? $a['damage'] / $a['mana_cost'] : $a['damage'];
                $efficiencyB = $b['mana_cost'] > 0 ? $b['damage'] / $b['mana_cost'] : $b['damage'];

                return $efficiencyB <=> $efficiencyA;
            });

            // 如果最高效率的技能比普通攻击好很多，使用它
            $bestSkill = $skillsWithDamage[0];
            $bestEfficiency = $bestSkill['mana_cost'] > 0 ? $bestSkill['damage'] / $bestSkill['mana_cost'] : $bestSkill['damage'];
            $baseEfficiency = $baseAttackDamage; // 普通攻击视为消耗0魔法

            // 只有当技能效率明显优于普通攻击时才使用
            if ($bestEfficiency >= $baseEfficiency * 0.5 || $bestSkill['damage'] > $totalMonsterHp * 0.5) {
                return $bestSkill;
            }
        }

        // 默认：使用最经济的技能
        usort($availableSkills, function ($a, $b) {
            if ($a['mana_cost'] === 0 && $b['mana_cost'] > 0) {
                return -1;
            }
            if ($b['mana_cost'] === 0 && $a['mana_cost'] > 0) {
                return 1;
            }

            return $a['mana_cost'] <=> $b['mana_cost'];
        });

        return $availableSkills[0];
    }

    /**
     * 选择本回合攻击目标（AOE 为全部存活，单体为随机一只）
     *
     * @param  array<int, array<string, mixed>>  $monsters
     * @return array<int, array<string, mixed>>
     */
    private function selectRoundTargets(array $monsters, bool $isAoeSkill): array
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
     * 对目标怪物施加角色伤害，返回更新后的怪物列表与总伤害
     *
     * @param  array<int, array<string, mixed>>  $monsters
     * @param  array<int, array<string, mixed>>  $targetMonsters
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function applyCharacterDamageToMonsters(
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
            $m['damage_taken'] = -1; // -1 表示未受攻击
            $m['was_attacked'] = false; // 每回合开始时清除被攻击标记

            // 新出现的怪物不受攻击（下一轮才能攻击）
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
            $m['was_attacked'] = true; // 标记该怪物被攻击
            $totalDamageDealt += $targetDamage;
            $monstersUpdated[$idx] = $m;
        }

        // 清除所有新怪物标记 现在可以攻击了
        foreach ($monstersUpdated as $idx => $m) {
            if (isset($m['is_new'])) {
                unset($monstersUpdated[$idx]['is_new']);
            }
        }

        return [$monstersUpdated, $totalDamageDealt];
    }

    /**
     * 按槽位 position 判断是否为攻击目标（同种同等级多只怪物时只命中选中的那一只）
     *
     * @param  array<string, mixed>  $monster
     * @param  array<int, array<string, mixed>>  $targets
     */
    private function isMonsterInTargets(array $monster, array $targets): bool
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
     * 计算所有存活怪物对角色造成的总反击伤害
     *
     * @param  array<int, array<string, mixed>>  $monstersUpdated
     */
    private function calculateMonsterCounterDamage(array $monstersUpdated, int $charDefense): int
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
     * 获取本回合开始时存活的怪物列表
     *
     * @param  array<int, array<string, mixed>>  $monsters
     * @return array<int, array<string, mixed>>
     */
    private function getAliveMonsters(array $monsters): array
    {
        return array_filter($monsters, fn ($m) => ($m['hp'] ?? 0) > 0);
    }

    /**
     * 记录本回合开始时每个槽位的怪物血量
     *
     * @param  array<int, array<string, mixed>>  $monsters
     * @return array<int, int>
     */
    private function getMonsterHpSnapshot(array $monsters): array
    {
        $hpAtRoundStart = [];
        foreach ($monsters as $idx => $m) {
            $hpAtRoundStart[$idx] = $m['hp'] ?? 0;
        }

        return $hpAtRoundStart;
    }

    /**
     * 收集技能命中的目标位置
     *
     * @param  array<int, array<string, mixed>>  $targetMonsters
     * @return array<int, int>
     */
    private function getSkillTargetPositions(array $targetMonsters): array
    {
        $positions = array_map(fn ($m) => $m['position'] ?? null, $targetMonsters);

        return array_values(array_filter($positions, fn ($p) => $p !== null));
    }

    /**
     * 计算基础攻击伤害与暴击额外伤害
     *
     * @param  array<int, array<string, mixed>>  $targetMonsters
     * @return array{0: int, 1: int}
     */
    private function computeBaseAttackDamage(
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
     * 获取第一个存活的怪物
     *
     * @param  array<int, array<string, mixed>>  $monstersUpdated
     * @return array<string, mixed>|null
     */
    private function getFirstAliveMonster(array $monstersUpdated): ?array
    {
        foreach ($monstersUpdated as $m) {
            if (($m['hp'] ?? 0) > 0) {
                return $m;
            }
        }

        return null;
    }

    /**
     * 收集详细信息用于日志
     *
     * @return array<string, mixed>
     */
    private function buildRoundDetails(
        GameCharacter $character,
        ?array $firstAliveMonster,
        int $charAttack,
        int $charDefense,
        float $charCritRate,
        float $charCritDamage,
        int $baseAttackDamage,
        int $skillDamage,
        int $critDamageAmount,
        int $aoeDamageAmount,
        int $totalDamageDealt,
        float $defenseReduction,
        int $totalMonsterDamage,
        int $currentRound,
        int $aliveMonsterCount,
        int $monstersKilledThisRound,
        bool $isCrit,
        bool $useAoe,
        array $difficulty
    ): array {
        return [
            'character' => [
                'level' => $character->level,
                'class' => $character->class,
                'attack' => $charAttack,
                'defense' => $charDefense,
                'crit_rate' => $charCritRate,
                'crit_damage' => $charCritDamage,
            ],
            'monster' => $firstAliveMonster ? [
                'level' => $firstAliveMonster['level'] ?? 1,
                'hp' => $firstAliveMonster['hp'] ?? 0,
                'max_hp' => $firstAliveMonster['max_hp'] ?? 0,
                'attack' => $firstAliveMonster['attack'] ?? 0,
                'defense' => $firstAliveMonster['defense'] ?? 0,
                'experience' => $firstAliveMonster['experience'] ?? 0,
            ] : null,
            'damage' => [
                'base_attack' => $baseAttackDamage,
                'skill_damage' => $skillDamage,
                'crit_damage' => $critDamageAmount,
                'aoe_damage' => $aoeDamageAmount,
                'total' => $totalDamageDealt,
                'defense_reduction' => $defenseReduction,
                'monster_counter' => $totalMonsterDamage,
            ],
            'battle' => [
                'round' => $currentRound,
                'alive_count' => $aliveMonsterCount,
                'killed_count' => $monstersKilledThisRound,
                'is_crit' => $isCrit,
                'is_aoe' => $useAoe,
            ],
            'difficulty' => [
                'tier' => $character->difficulty_tier ?? 0,
                'multiplier' => $difficulty['reward'] ?? 1,
            ],
        ];
    }

    /**
     * 将本回合技能使用合并到累计统计
     *
     * @param  array<int, array{skill_id: int, name: string, icon: string|null}>  $skillsUsedThisRound
     * @param  array<int|string, array{skill_id: int, name: string, icon: string|null, use_count: int}>  $skillsUsedAggregated
     * @return array<int, array{skill_id: int, name: string, icon: string|null, use_count: int}>
     */
    private function aggregateSkillsUsed(array $skillsUsedThisRound, array $skillsUsedAggregated): array
    {
        $aggregated = $skillsUsedAggregated;
        foreach ($skillsUsedThisRound as $entry) {
            $id = $entry['skill_id'];
            if (! isset($aggregated[$id])) {
                $aggregated[$id] = [
                    'skill_id' => $entry['skill_id'],
                    'name' => $entry['name'],
                    'icon' => $entry['icon'] ?? null,
                    'use_count' => 0,
                ];
            }
            $aggregated[$id]['use_count']++;
        }

        return array_values($aggregated);
    }

    /**
     * @param  array<int, array<string, mixed>>  $monstersUpdated
     */
    private function hasAliveMonster(array $monstersUpdated): bool
    {
        foreach ($monstersUpdated as $m) {
            if (($m['hp'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 计算本回合死亡怪物的经验与铜币奖励（已乘难度系数）
     *
     * @param  array<int, array<string, mixed>>  $monstersUpdated
     * @param  array<int, int>  $hpAtRoundStart
     * @return array{0: int, 1: int}
     */
    private function calculateRoundDeathRewards(
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

                // 使用怪物定义的 drop_table 配置计算铜币掉落
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
    private function calculateMonsterCopperLoot(array $monster): int
    {
        $monsterId = $monster['id'] ?? null;
        if (! $monsterId) {
            return rand(1, 10); // 回退到随机铜币
        }

        $definition = GameMonsterDefinition::query()->find($monsterId);
        if (! $definition) {
            return rand(1, 10); // 回退到随机铜币
        }

        $dropTable = $definition->drop_table ?? [];
        $level = $monster['level'] ?? $definition->level;

        // 优先使用怪物 drop_table 的配置，否则用全局配置
        $copperConfig = config('game.copper_drop');
        if (! empty($dropTable['copper_chance'])) {
            $copperChance = $dropTable['copper_chance'];
            $base = (int) ($dropTable['copper_base'] ?? $copperConfig['base']);
            $range = (int) ($dropTable['copper_range'] ?? $copperConfig['range']);
        } else {
            $copperChance = $copperConfig['chance'];
            $base = $copperConfig['base'];
            $range = $copperConfig['range'];
        }

        if (! $this->rollChanceForProcessor($copperChance)) {
            return 0;
        }

        return random_int($base, $base + $range);
    }

    /**
     * 概率判定（复制自 GameMonsterDefinition）
     */
    private function rollChanceForProcessor(float $chance): bool
    {
        // $chance是0~1，例如0.12就是12%概率
        return mt_rand() / mt_getrandmax() < $chance;
    }
}
