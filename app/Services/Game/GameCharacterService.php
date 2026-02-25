<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 角色服务类
 *
 * 负责角色相关的业务逻辑，包括创建、删除、属性分配、离线奖励等
 */
class GameCharacterService
{
    /** 缓存键前缀 */
    private const CACHE_PREFIX = 'game_character:';

    /** 缓存有效期（秒） */
    private const CACHE_TTL = 300;

    /**
     * 获取用户角色列表
     *
     * @param  int  $userId  用户ID
     * @return array 角色列表和经验表
     */
    public function getCharacterList(int $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . "list:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            $characters = GameCharacter::query()
                ->where('user_id', $userId)
                ->get();

            // 批量处理等级同步
            $characters->each(fn ($character) => $character->reconcileLevelFromExperience());

            return [
                'characters' => $characters->map(fn ($c) => $c->only([
                    'id', 'name', 'class', 'level', 'experience', 'copper', 'is_fighting', 'difficulty_tier',
                ])),
                'experience_table' => config('game.experience_table', []),
            ];
        });
    }

    /**
     * 获取角色详情
     *
     * @param  int  $userId  用户ID
     * @param  int|null  $characterId  角色ID（可选）
     * @return array|null 角色详情数组
     */
    public function getCharacterDetail(int $userId, ?int $characterId = null): ?array
    {
        $query = GameCharacter::query()
            ->where('user_id', $userId)
            ->with([
                'equipment.item.definition',
                'skills.skill',
                'currentMap',
            ]);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        $character = $query->first();

        if (! $character) {
            return null;
        }

        $character->reconcileLevelFromExperience();

        return [
            'character' => $character,
            'experience_table' => config('game.experience_table', []),
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'equipped_items' => $character->getEquippedItems(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ];
    }

    /**
     * 创建新角色
     *
     * @param  int  $userId  用户ID
     * @param  string  $name  角色名称
     * @param  string  $class  职业类型
     * @param  string  $gender  性别 male 或 female
     * @return GameCharacter 创建的角色
     *
     * @throws \InvalidArgumentException 角色名已存在
     */
    public function createCharacter(int $userId, string $name, string $class, string $gender = 'male'): GameCharacter
    {
        // 验证角色名
        $this->validateCharacterName($name);

        // 检查角色名是否已存在
        if ($this->isCharacterNameTaken($name)) {
            throw new \InvalidArgumentException('角色名已被使用');
        }

        // 获取职业配置
        $classStats = $this->getClassBaseStats($class);

        return DB::transaction(function () use ($userId, $name, $class, $gender, $classStats) {
            // 创建角色
            $character = GameCharacter::create([
                'user_id' => $userId,
                'name' => $name,
                'class' => $class,
                'gender' => $gender,
                'level' => 1,
                'experience' => 0,
                'copper' => $this->getStartingCopper($class),
                'strength' => $classStats['strength'],
                'dexterity' => $classStats['dexterity'],
                'vitality' => $classStats['vitality'],
                'energy' => $classStats['energy'],
                'skill_points' => 0,
                'stat_points' => 0,
            ]);

            // 初始化装备槽位
            $this->initializeEquipmentSlots($character);

            // 清除缓存
            $this->clearCharacterCache($userId);

            return $character;
        });
    }

    /**
     * 删除角色
     *
     * @param  int  $userId  用户ID
     * @param  int  $characterId  角色ID
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 角色不存在
     */
    public function deleteCharacter(int $userId, int $characterId): void
    {
        $character = GameCharacter::query()
            ->where('id', $characterId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $character->delete();

        // 清除缓存
        $this->clearCharacterCache($userId);
    }

    /**
     * 分配属性点
     *
     * @param  int  $userId  用户ID
     * @param  int  $characterId  角色ID
     * @param  array  $stats  要分配的属性 ['strength' => 1, 'dexterity' => 2, ...]
     * @return array 更新后的角色数据
     *
     * @throws \InvalidArgumentException 属性点不足
     */
    public function allocateStats(int $userId, int $characterId, array $stats): array
    {
        $character = GameCharacter::query()
            ->where('id', $characterId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // 验证并计算总分配点数
        $totalPoints = $this->calculateTotalStatPoints($stats);

        // 验证是否有足够的属性点
        if ($totalPoints > $character->stat_points) {
            throw new \InvalidArgumentException('属性点不足');
        }

        // 更新属性
        $character->fill([
            'strength' => $character->strength + ($stats['strength'] ?? 0),
            'dexterity' => $character->dexterity + ($stats['dexterity'] ?? 0),
            'vitality' => $character->vitality + ($stats['vitality'] ?? 0),
            'energy' => $character->energy + ($stats['energy'] ?? 0),
            'stat_points' => $character->stat_points - $totalPoints,
        ]);
        $character->save();

        return [
            'character' => $character,
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ];
    }

    /**
     * 更新难度设置
     *
     * @param  int  $userId  用户ID
     * @param  int  $difficultyTier  难度等级 (1-4)
     * @param  int|null  $characterId  角色ID（可选）
     * @return GameCharacter 更新后的角色
     */
    public function updateDifficulty(int $userId, int $difficultyTier, ?int $characterId = null): GameCharacter
    {
        $query = GameCharacter::query()->where('user_id', $userId);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        $character = $query->firstOrFail();
        $character->difficulty_tier = $difficultyTier;
        $character->save();

        return $character;
    }

    /**
     * 获取角色完整详情（包含背包、技能等）
     *
     * @param  int  $userId  用户ID
     * @param  int|null  $characterId  角色ID（可选）
     * @return array 完整角色数据
     */
    public function getCharacterFullDetail(int $userId, ?int $characterId = null): array
    {
        $query = GameCharacter::query()->where('user_id', $userId);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        $character = $query->firstOrFail();

        // 使用 eager loading 优化查询
        $inventory = $character->items()
            ->where('is_in_storage', false)
            ->with('definition')
            ->orderBy('slot_index')
            ->get();

        $storage = $character->items()
            ->where('is_in_storage', true)
            ->with('definition')
            ->orderBy('slot_index')
            ->get();

        $skills = $character->skills()
            ->with('skill')
            ->orderBy('slot_index')
            ->get();

        $availableSkills = $this->getAvailableSkills($character);

        return [
            'character' => $character,
            'inventory' => $inventory,
            'storage' => $storage,
            'skills' => $skills,
            'available_skills' => $availableSkills,
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ];
    }

    /**
     * 检查离线奖励信息
     *
     * @param  GameCharacter  $character  角色实例
     * @return array 离线奖励信息
     */
    public function checkOfflineRewards(GameCharacter $character): array
    {
        $lastOnline = $character->last_online;

        if (! $lastOnline) {
            return $this->formatOfflineRewards(0, false);
        }

        $now = now();
        $offlineSeconds = $now->diffInSeconds($lastOnline);

        // 最小60秒才发放离线奖励
        if ($offlineSeconds < 60) {
            return $this->formatOfflineRewards((int) $offlineSeconds, false);
        }

        // 最多24小时（从配置读取）
        $maxSeconds = config('game.offline_rewards.max_seconds', 86400);
        $offlineSeconds = min($offlineSeconds, $maxSeconds);

        // 计算奖励（从配置读取系数）
        $level = $character->level;
        $expPerLevel = config('game.offline_rewards.experience_per_level', 1);
        $copperPerLevel = config('game.offline_rewards.copper_per_level', 0.5);
        $experience = (int) ($level * $offlineSeconds * $expPerLevel);
        $copper = (int) ($level * $offlineSeconds * $copperPerLevel);

        // 检查是否升级
        $currentExp = $character->experience;
        $expNeeded = $character->getExperienceToNextLevel();
        $newExp = $currentExp + $experience;
        $levelUp = $newExp >= $expNeeded;

        return $this->formatOfflineRewards((int) $offlineSeconds, true, $experience, $copper, $levelUp);
    }

    /**
     * 领取离线奖励
     *
     * @param  GameCharacter  $character  角色实例
     * @return array 领取结果
     */
    public function claimOfflineRewards(GameCharacter $character): array
    {
        $rewardInfo = $this->checkOfflineRewards($character);

        if (! $rewardInfo['available']) {
            return [
                'experience' => 0,
                'copper' => 0,
                'level_up' => false,
                'new_level' => $character->level,
            ];
        }

        $originalLevel = $character->level;

        // 更新经验
        $character->experience += $rewardInfo['experience'];
        $character->reconcileLevelFromExperience();

        // 更新铜币
        $character->copper += $rewardInfo['copper'];

        // 更新最后领取时间
        $character->claimed_offline_at = now();
        $character->save();

        return [
            'experience' => $rewardInfo['experience'],
            'copper' => $rewardInfo['copper'],
            'level_up' => $character->level > $originalLevel,
            'new_level' => $character->level,
        ];
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 验证角色名称
     *
     * @param  string  $name  角色名称
     *
     * @throws \InvalidArgumentException 名称不符合要求
     */
    private function validateCharacterName(string $name): void
    {
        $length = mb_strlen($name);

        if ($length < 2) {
            throw new \InvalidArgumentException('角色名至少需要2个字符');
        }

        if ($length > 12) {
            throw new \InvalidArgumentException('角色名最多12个字符');
        }

        if (! preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u', $name)) {
            throw new \InvalidArgumentException('角色名只能包含中文、英文和数字');
        }
    }

    /**
     * 检查角色名是否已被使用
     *
     * @param  string  $name  角色名称
     * @return bool 是否已被使用
     */
    private function isCharacterNameTaken(string $name): bool
    {
        return GameCharacter::query()->where('name', $name)->exists();
    }

    /**
     * 获取职业基础属性
     *
     * @param  string  $class  职业类型
     * @return array 基础属性数组
     */
    private function getClassBaseStats(string $class): array
    {
        return config("game.class_base_stats.{$class}", [
            'strength' => 2,
            'dexterity' => 3,
            'vitality' => 2,
            'energy' => 2,
        ]);
    }

    /**
     * 获取初始铜币
     *
     * @param  string  $class  职业类型
     * @return int 初始铜币数量
     */
    private function getStartingCopper(string $class): int
    {
        return config("game.starting_copper.{$class}", 0);
    }

    /**
     * 初始化装备槽位
     *
     * @param  GameCharacter  $character  角色实例
     */
    private function initializeEquipmentSlots(GameCharacter $character): void
    {
        foreach (GameCharacter::getSlots() as $slot) {
            $character->equipment()->create(['slot' => $slot]);
        }
    }

    /**
     * 计算总属性点数
     *
     * @param  array  $stats  属性数组
     * @return int 总点数
     */
    private function calculateTotalStatPoints(array $stats): int
    {
        return array_sum(array_map(fn ($v) => max(0, (int) $v), $stats));
    }

    /**
     * 获取可用技能列表
     *
     * @param  GameCharacter  $character  角色实例
     * @return \Illuminate\Database\Eloquent\Collection 可用技能集合
     */
    private function getAvailableSkills(GameCharacter $character)
    {
        return \App\Models\Game\GameSkillDefinition::query()
            ->where('is_active', true)
            ->where(function ($query) use ($character) {
                $query->where('class_restriction', 'all')
                    ->orWhere('class_restriction', $character->class);
            })
            ->get();
    }

    /**
     * 格式化离线奖励返回数据
     *
     * @param  int  $offlineSeconds  离线秒数
     * @param  bool  $available  是否可领取
     * @param  int  $experience  经验（可选）
     * @param  int  $copper  铜币（可选）
     * @param  bool  $levelUp  是否升级（可选）
     * @return array 格式化后的数据
     */
    private function formatOfflineRewards(
        int $offlineSeconds,
        bool $available,
        int $experience = 0,
        int $copper = 0,
        bool $levelUp = false
    ): array {
        return [
            'available' => $available,
            'offline_seconds' => $offlineSeconds,
            'experience' => $experience,
            'copper' => $copper,
            'level_up' => $levelUp,
        ];
    }

    /**
     * 清除角色相关缓存
     *
     * @param  int  $userId  用户ID
     */
    private function clearCharacterCache(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . "list:{$userId}");
    }
}
