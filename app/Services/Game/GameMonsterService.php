<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Support\Carbon;

class GameMonsterService
{
    // 怪物刷新间隔（秒），从配置中读取，默认60秒
    protected function getRefreshInterval(): int
    {
        $val = config('game.combat.monster_refresh_interval', 60);

        return is_numeric($val) ? (int) $val : 60;
    }

    /**
     * 检查怪物是否需要刷新
     */
    public function shouldRefreshMonsters(GameCharacter $character): bool
    {
        $refreshedAt = $character->combat_monsters_refreshed_at;
        if (! $refreshedAt instanceof Carbon) {
            return true;
        }

        $interval = $this->getRefreshInterval();

        return $refreshedAt->addSeconds($interval)->isPast();
    }

    /**
     * 从角色获取现有怪物或生成新怪物
     *
     * @return array{0: ?\App\Models\Game\GameMonsterDefinition,1: ?int,2: ?array<string,int>,3: int,4: int}
     */
    public function prepareMonsterInfo(GameCharacter $character, GameMapDefinition $map): array
    {
        $existingMonsters = $character->combat_monsters ?? [];

        // 检查是否有存活怪物
        $hasAliveMonster = false;
        foreach ($existingMonsters as $m) {
            if (is_array($m) && ($m['hp'] ?? 0) > 0) {
                $hasAliveMonster = true;
                break;
            }
        }

        // 检查是否需要刷新怪物（定期从数据库读取最新属性）
        $shouldRefresh = $this->shouldRefreshMonsters($character);

        if ($character->hasActiveCombat() && $hasAliveMonster && ! $shouldRefresh) {
            return $this->loadExistingMonsters($character, $existingMonsters);
        }

        // 需要刷新怪物：重新生成
        return $this->generateNewMonsters($character, $map, $existingMonsters, $shouldRefresh);
    }

    /**
     * 从角色状态加载现有怪物
     *
     * @param  array<int, array<string,mixed>|null>  $existingMonsters
     * @return array{0: ?\App\Models\Game\GameMonsterDefinition,1: ?int,2: ?array<string,int>,3: int,4: int}
     */
    public function loadExistingMonsters(GameCharacter $character, array $existingMonsters): array
    {
        $firstMonster = null;
        $monsterLevel = null;
        $monsterStats = null;
        $totalHp = 0;
        $totalMaxHp = 0;

        foreach ($existingMonsters as $m) {
            if (! is_array($m)) {
                continue;
            }
            if ($firstMonster === null && ($m['hp'] ?? 0) > 0) {
                $monster = null;
                if (isset($m['id']) && (is_int($m['id']) || is_string($m['id']) || is_numeric($m['id']))) {
                    $monster = GameMonsterDefinition::query()->find($m['id']);
                }
                if ($monster instanceof GameMonsterDefinition) {
                    $firstMonster = $monster;
                    $monsterLevel = isset($m['level']) && is_numeric($m['level']) ? (int) $m['level'] : null;
                    /** @var \App\Models\Game\GameMonsterDefinition $monster */
                    $monsterStats = $monster->getCombatStats();
                }
            }
            $totalHp += isset($m['hp']) && is_numeric($m['hp']) ? (int) $m['hp'] : 0;
            $totalMaxHp += isset($m['max_hp']) && is_numeric($m['max_hp']) ? (int) $m['max_hp'] : 0;
        }

        if (! $firstMonster) {
            $character->clearCombatState();

            return [null, null, null, 0, 0];
        }

        return [$firstMonster, $monsterLevel, $monsterStats, (int) $totalHp, (int) $totalMaxHp];
    }

    /**
     * 生成新怪物 (1-5个)
     *
     * @param  array<int, array<string,mixed>|null>  $existingMonsters
     * @return array{0: ?\App\Models\Game\GameMonsterDefinition,1: ?int,2: ?array<string,int>,3: int,4: int}
     */
    public function generateNewMonsters(GameCharacter $character, GameMapDefinition $map, array $existingMonsters, bool $isRefresh = false): array
    {
        $monsters = $map->getMonsters();
        /** @var array<int, \App\Models\Game\GameMonsterDefinition> $monsters */
        if (empty($monsters)) {
            return [null, null, null, 0, 0];
        }

        $difficulty = $character->getDifficultyMultipliers();
        $monsterHpMultiplier = (float) $difficulty['monster_hp'];
        $monsterDamageMultiplier = (float) $difficulty['monster_damage'];
        $rewardMultiplier = (float) $difficulty['reward'];

        // 随机生成 1-5 个怪物
        $monsterCount = rand(1, 5);
        $baseMonster = $monsters[array_rand($monsters)];
        $baseLevel = max(1, $baseMonster->level + rand(-3, 3));

        // 如果是刷新，保留现有怪物的HP
        $existingByPosition = [];
        if ($isRefresh && ! empty($existingMonsters)) {
            foreach ($existingMonsters as $m) {
                if (is_array($m) && isset($m['position'])) {
                    $existingByPosition[$m['position']] = $m;
                }
            }
        }

        $monsterDataList = [];
        for ($i = 0; $i < $monsterCount; $i++) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats();
            $hpBase = (int) $stats['hp'];
            $maxHp = (int) ($hpBase * $monsterHpMultiplier);

            // 固定槽位位置，确保刷新时怪物位置一致
            $slot = $i;

            // 如果是刷新且该位置有现有怪物，保留HP
            $hp = $maxHp;
            if ($isRefresh && isset($existingByPosition[$slot])) {
                $existing = $existingByPosition[$slot];
                // 保持现有HP，但不超出新maxHp
                $hp = min(isset($existing['hp']) && is_numeric($existing['hp']) ? (int) $existing['hp'] : $maxHp, $maxHp);
            }

            $attackBase = (int) $stats['attack'];
            $defBase = (int) $stats['defense'];
            $expBase = (int) $stats['experience'];

            $monsterDataList[] = [
                'id' => $baseMonster->id,
                'instance_id' => $isRefresh && isset($existingByPosition[$slot])
                    ? ($existingByPosition[$slot]['instance_id'] ?? uniqid('m-', true))
                    : uniqid('m-', true),
                'name' => $baseMonster->name,
                'type' => $baseMonster->type,
                'level' => $level,
                'hp' => $hp,
                'max_hp' => $maxHp,
                'attack' => (int) ($attackBase * $monsterDamageMultiplier),
                'defense' => (int) ($defBase * $monsterDamageMultiplier),
                'experience' => (int) ($expBase * $rewardMultiplier),
                'position' => $slot,
                'damage_taken' => -1, // 新怪物未被攻击
            ];
        }

        // 固定5个槽位(0-4)
        $newMonsters = array_fill(0, 5, null);
        foreach ($monsterDataList as $data) {
            $slot = $data['position'];
            $newMonsters[$slot] = $data;
        }

        // 持久化怪物数组（5个槽位，可能包含null）
        $character->combat_monsters = $newMonsters;
        // 更新刷新时间戳
        $character->combat_monsters_refreshed_at = now();
        $character->combat_monster_id = $baseMonster->id;
        $character->combat_monster_hp = (int) array_sum(array_column(array_filter($newMonsters, 'is_array'), 'hp'));
        $character->combat_monster_max_hp = (int) array_sum(array_column(array_filter($newMonsters, 'is_array'), 'max_hp'));
        $character->combat_total_damage_dealt = 0;
        $character->combat_total_damage_taken = 0;
        $character->combat_rounds = 0;
        $character->combat_skills_used = null;
        $character->combat_skill_cooldowns = null;
        $character->combat_started_at = now();
        $character->save();

        // 第一个怪物：槽位顺序中第一个存活的怪物
        $firstMonster = null;
        for ($slot = 0; $slot < 5; $slot++) {
            $m = $newMonsters[$slot] ?? null;
            if (is_array($m) && ($m['hp'] ?? 0) > 0) { // @phpstan-ignore function.alreadyNarrowedType, nullCoalesce.offset
                $firstMonster = $m;
                break;
            }
        }
        $firstMonster = $firstMonster ?? $monsterDataList[0];
        $monster = null;
        $monsterStats = null;
        $firstLevel = null;
        $monster = GameMonsterDefinition::query()->find($firstMonster['id']);
        /** @var \App\Models\Game\GameMonsterDefinition|null $monster */
        $monsterStats = $monster ? $monster->getCombatStats() : null;
        $firstLevel = (int) $firstMonster['level'];

        return [
            $monster,
            $firstLevel,
            $monsterStats,
            $character->combat_monster_hp,
            $character->combat_monster_max_hp,
        ];
    }

    /**
     * 每回合按概率尝试补充新怪物：30% 不生成，70% 按权重生成 1～5 只（1 只概率最大，依次递减）
     * 空槽位 = 未占用或怪物已死亡，每回合都可能补怪，不要求全部死亡才刷新
     */
    /**
     * @param  array<string,mixed>  $roundResult
     * @return array<string,mixed>
     */
    public function tryAddNewMonsters(GameCharacter $character, GameMapDefinition $map, array $roundResult, int $currentRound): array
    {
        $currentMonsters = $character->combat_monsters ?? [];
        $indexed = $currentMonsters === [] ? [] : array_values($currentMonsters);
        $currentMonsters = array_pad($indexed, 5, null);

        // 空槽位：未设置、null、或怪物已死亡（hp<=0）
        $emptySlots = [];
        for ($i = 0; $i < 5; $i++) {
            $m = $currentMonsters[$i] ?? null;
            if ($m === null || ! is_array($m) || ($m['hp'] ?? 0) <= 0) { // @phpstan-ignore function.alreadyNarrowedType
                $emptySlots[] = $i;
            }
        }
        // 本回合刚死亡的槽位不生成新怪，避免新怪与死亡动画重叠，下一回合该槽位可再参与
        $justDiedSlots = $roundResult['slots_where_monster_died_this_round'] ?? [];
        $fillableSlots = array_values(array_diff($emptySlots, $justDiedSlots));
        $canAdd = count($fillableSlots);
        if ($canAdd <= 0) {
            $this->syncRoundResultMonsterHp($roundResult, $currentMonsters);

            return $roundResult;
        }

        // 30% 不生成
        if (rand(1, 100) <= 30) {
            $this->syncRoundResultMonsterHp($roundResult, $currentMonsters);

            return $roundResult;
        }

        // 70% 生成：权重 1(40%) > 2(25%) > 3(20%) > 4(10%) > 5(5%)
        $roll = rand(1, 100);
        $wantCount = 1;
        if ($roll <= 40) {
            $wantCount = 1;
        } elseif ($roll <= 65) {
            $wantCount = 2;
        } elseif ($roll <= 85) {
            $wantCount = 3;
        } elseif ($roll <= 95) {
            $wantCount = 4;
        } else {
            $wantCount = 5;
        }
        $addCount = min($canAdd, $wantCount);

        $difficulty = $character->getDifficultyMultipliers();
        $monsterHpMultiplier = (float) $difficulty['monster_hp'];
        $monsterDamageMultiplier = (float) $difficulty['monster_damage'];
        $rewardMultiplier = (float) $difficulty['reward'];
        $monsters = $map->getMonsters();
        if (empty($monsters)) {
            $this->syncRoundResultMonsterHp($roundResult, $currentMonsters);

            return $roundResult;
        }

        shuffle($fillableSlots);
        $slotsToFill = array_slice($fillableSlots, 0, $addCount);

        $baseMonster = $monsters[array_rand($monsters)];
        $baseLevel = max(1, $baseMonster->level + rand(-3, 3));

        foreach ($slotsToFill as $slot) {
            $level = $baseLevel + rand(-1, 1);
            $level = max(1, $level);
            $stats = $baseMonster->getCombatStats();
            $hpVal = (int) $stats['hp'];
            $maxHp = (int) ($hpVal * $monsterHpMultiplier);

            $attackVal = (int) $stats['attack'];
            $defVal = (int) $stats['defense'];
            $expVal = (int) $stats['experience'];

            $currentMonsters[$slot] = [
                'id' => $baseMonster->id,
                'instance_id' => uniqid('m-', true), // 唯一实例ID，用于前端检测新怪物
                'name' => $baseMonster->name,
                'type' => $baseMonster->type,
                'level' => $level,
                'hp' => $maxHp,
                'max_hp' => $maxHp,
                'attack' => (int) ($attackVal * $monsterDamageMultiplier),
                'defense' => (int) ($defVal * $monsterDamageMultiplier),
                'experience' => (int) ($expVal * $rewardMultiplier),
                'position' => $slot,
            ];
        }

        $character->combat_monsters = $currentMonsters;
        $this->syncRoundResultMonsterHp($roundResult, $currentMonsters);

        return $roundResult;
    }

    /**
     * 将当前怪物列表的 hp/max_hp 合计写入 roundResult
     */
    /**
     * @param  array<string,mixed>  $roundResult
     * @param  array<int, array<string,mixed>|null>  $currentMonsters
     */
    private function syncRoundResultMonsterHp(array &$roundResult, array $currentMonsters): void
    {
        $alive = array_filter($currentMonsters, fn ($m) => is_array($m));
        $roundResult['new_monster_hp'] = array_sum(array_column($alive, 'hp'));
        $roundResult['new_monster_max_hp'] = array_sum(array_column($alive, 'max_hp'));
    }

    /**
     * 格式化怪物用于响应（固定5个槽位）
     */
    /**
     * @return array{monsters: array<int, array<string,mixed>|null>, first_alive_monster: array<string,mixed>}
     */
    public function formatMonstersForResponse(GameCharacter $character): array
    {
        $currentMonsters = $character->combat_monsters ?? [];
        $fixedMonsters = array_fill(0, 5, null);
        for ($idx = 0; $idx < 5; $idx++) {
            $m = $currentMonsters[$idx] ?? null;
            if (is_array($m)) {
                $m['position'] = $idx;
                $fixedMonsters[$idx] = $m;
            }
        }

        // 查找第一个存活怪物
        $firstAliveMonster = null;
        foreach ($fixedMonsters as $m) {
            if ($m && ($m['hp'] ?? 0) > 0) {
                $firstAliveMonster = $m;
                break;
            }
        }
        if (! $firstAliveMonster) {
            $firstAliveMonster = ['name' => '怪物', 'type' => 'normal', 'level' => 1];
        }

        return [
            'monsters' => $fixedMonsters,
            'first_alive_monster' => $firstAliveMonster,
        ];
    }
}
