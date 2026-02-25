<?php

namespace App\Models\Game;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, array<string,mixed>|null>|null $combat_monsters
 * @property \Illuminate\Support\Carbon|null $combat_monsters_refreshed_at
 * @property int|null $combat_monster_id
 * @property int|null $combat_monster_hp
 * @property int|null $combat_monster_max_hp
 * @property array<int, int>|null $discovered_items
 * @property array<int, int>|null $discovered_monsters
 * @property \App\Models\Game\GameMapDefinition|null $currentMap
 */
class GameCharacter extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'class',
        'gender',
        'level',
        'experience',
        'copper',
        'strength',
        'dexterity',
        'vitality',
        'energy',
        'skill_points',
        'stat_points',
        'current_map_id',
        'is_fighting',
        'last_combat_at',
        'difficulty_tier',
        'current_hp',
        'current_mana',
        'auto_use_hp_potion',
        'hp_potion_threshold',
        'auto_use_mp_potion',
        'mp_potion_threshold',
        'combat_monster_id',
        'combat_monster_hp',
        'combat_monster_max_hp',
        'combat_monsters',
        'combat_monsters_refreshed_at',
        'combat_total_damage_dealt',
        'combat_total_damage_taken',
        'combat_rounds',
        'combat_skills_used',
        'combat_skill_cooldowns',
        'combat_started_at',
        'last_online',
        'claimed_offline_at',
        'discovered_items',
        'discovered_monsters',
    ];

    protected function casts(): array
    {
        return [
            'is_fighting' => 'boolean',
            'last_combat_at' => 'datetime',
            'auto_use_hp_potion' => 'boolean',
            'auto_use_mp_potion' => 'boolean',
            'hp_potion_threshold' => 'integer',
            'mp_potion_threshold' => 'integer',
            'combat_skills_used' => 'array',
            'combat_skill_cooldowns' => 'array',
            'combat_monsters' => 'array',
            'combat_monsters_refreshed_at' => 'datetime',
            'combat_started_at' => 'datetime',
            'last_online' => 'datetime',
            'claimed_offline_at' => 'datetime',
            'discovered_items' => 'array',
            'discovered_monsters' => 'array',
        ];
    }

    /**
     * 装备槽位列表
     *
     * @return array<int, string>
     */
    public static function getSlots(): array
    {
        return config('game.slots', [
            'weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring1', 'ring2', 'amulet',
        ]);
    }

    /**
     * 获取所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取角色装备
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(GameEquipment::class, 'character_id');
    }

    /**
     * 获取背包物品
     */
    public function items(): HasMany
    {
        return $this->hasMany(GameItem::class, 'character_id');
    }

    /**
     * 获取已学技能
     */
    public function skills(): HasMany
    {
        return $this->hasMany(GameCharacterSkill::class, 'character_id');
    }

    /**
     * 获取当前地图
     */
    public function currentMap(): BelongsTo
    {
        return $this->belongsTo(GameMapDefinition::class, 'current_map_id');
    }

    /**
     * 获取战斗日志
     */
    public function combatLogs(): HasMany
    {
        return $this->hasMany(GameCombatLog::class, 'character_id');
    }

    /**
     * 是否处于一场战斗的进行中（有当前怪物且至少有一只存活）
     */
    public function hasActiveCombat(): bool
    {
        // 多怪物模式：检查 combat_monsters
        $monsters = $this->combat_monsters ?? [];
        if (! empty($monsters)) {
            foreach ($monsters as $monster) {
                if (($monster['hp'] ?? 0) > 0) {
                    return true;
                }
            }

            return false;
        }

        // 兼容旧数据：单怪物模式
        return $this->combat_monster_id !== null
            && (int) $this->combat_monster_hp > 0;
    }

    /**
     * 清除当前战斗状态（战斗结束或停止时调用）
     */
    public function clearCombatState(): void
    {
        $this->combat_monster_id = null;
        $this->combat_monster_hp = null;
        $this->combat_monster_max_hp = null;
        $this->combat_monsters = null;
        $this->combat_monsters_refreshed_at = null;
        $this->combat_total_damage_dealt = 0;
        $this->combat_total_damage_taken = 0;
        $this->combat_rounds = 0;
        $this->combat_skills_used = null;
        $this->combat_skill_cooldowns = null;
        $this->combat_started_at = null;
    }

    /**
     * 生命值基础值（仅配置中的 base，用于复活等）
     */
    public function getBaseHp(): int
    {
        $hpConfig = config('game.hp', []);
        $base = $hpConfig['base'] ?? [];

        return (int) ($base[$this->class] ?? ($base['default'] ?? 15));
    }

    /**
     * 计算最大生命值（基础 + 体力 + 装备加成）
     */
    public function getMaxHp(): int
    {
        $hpConfig = config('game.hp', []);
        $base = $hpConfig['base'] ?? [];
        $baseHp = $base[$this->class] ?? ($base['default'] ?? 15);
        $multiplier = $hpConfig['vitality_multiplier'] ?? 5;
        $equipmentBonus = (int) $this->getEquipmentBonus('max_hp');

        return (int) ($baseHp + $this->vitality * $multiplier) + $equipmentBonus;
    }

    /**
     * 法力值基础值（仅配置中的 base，用于复活等）
     */
    public function getBaseMana(): int
    {
        $manaConfig = config('game.mana', []);
        $base = $manaConfig['base'] ?? [];

        return (int) ($base[$this->class] ?? ($base['default'] ?? 15));
    }

    /**
     * 计算最大法力值（基础 + 精力 + 装备加成）
     */
    public function getMaxMana(): int
    {
        $manaConfig = config('game.mana', []);
        $base = $manaConfig['base'] ?? [];
        $baseMana = $base[$this->class] ?? ($base['default'] ?? 50);
        $multiplier = $manaConfig['energy_multiplier'] ?? 3;
        $equipmentBonus = (int) $this->getEquipmentBonus('max_mana');

        return (int) ($baseMana + $this->energy * $multiplier) + $equipmentBonus;
    }

    /**
     * 基础攻击力（不含装备）
     */
    public function getBaseAttack(): int
    {
        $attackConfig = config('game.combat.attack', []);
        $classConfig = $attackConfig[$this->class] ?? ($attackConfig['default'] ?? ['stat' => 'strength', 'multiplier' => 1]);
        $stat = $classConfig['stat'] ?? 'strength';
        $multiplier = (float) ($classConfig['multiplier'] ?? 1);

        return (int) ($this->{$stat} * $multiplier);
    }

    /**
     * 计算攻击力（力量/敏捷影响物理攻击，精力影响法术攻击）
     */
    public function getAttack(): int
    {
        return (int) ($this->getBaseAttack() + $this->getEquipmentBonus('attack'));
    }

    /**
     * 基础防御力（不含装备）
     */
    public function getBaseDefense(): int
    {
        $def = config('game.combat.defense', []);
        $vCoef = (float) ($def['vitality_multiplier'] ?? 0.5);
        $dCoef = (float) ($def['dexterity_multiplier'] ?? 0.3);

        return (int) ($this->vitality * $vCoef + $this->dexterity * $dCoef);
    }

    /**
     * 计算防御力（体力+敏捷影响防御）
     */
    public function getDefense(): int
    {
        return (int) ($this->getBaseDefense() + $this->getEquipmentBonus('defense'));
    }

    /**
     * 基础暴击率（不含装备，未封顶）
     */
    public function getBaseCritRate(): float
    {
        $critConfig = config('game.combat.crit_rate', []);
        $coef = (float) ($critConfig['dexterity_multiplier'] ?? 0.01);

        return $this->dexterity * $coef;
    }

    /**
     * 计算暴击率（敏捷影响暴击率）
     */
    public function getCritRate(): float
    {
        $critConfig = config('game.combat.crit_rate', []);
        $cap = (float) ($critConfig['cap'] ?? 0.10);

        return min($cap, $this->getBaseCritRate() + $this->getEquipmentBonus('crit_rate'));
    }

    /**
     * 基础暴击伤害倍率（不含装备）
     */
    public function getBaseCritDamage(): float
    {
        $critConfig = config('game.combat.crit_damage', []);

        return (float) ($critConfig['base'] ?? 1.5);
    }

    /**
     * 计算暴击伤害
     */
    public function getCritDamage(): float
    {
        return $this->getBaseCritDamage() + $this->getEquipmentBonus('crit_damage');
    }

    /**
     * 难度倍率：普通/困难/高手/大师/痛苦1-6（表格数据）
     * 怪物生命、怪物伤害、金币与经验分别使用对应加成。
     *
     * @return array{monster_hp: float, monster_damage: float, reward: float}
     */
    public function getDifficultyMultipliers(): array
    {
        $tier = (int) ($this->difficulty_tier ?? 0);
        $table = config('game.difficulty_multipliers', [0 => ['monster_hp' => 1.0, 'monster_damage' => 1.0, 'reward' => 1.0]]);

        return $table[$tier] ?? $table[0];
    }

    /**
     * 获取装备属性加成
     */
    public function getEquipmentBonus(string $stat): float
    {
        $bonus = 0;

        $equipmentSlots = $this->equipment()->with('item.definition', 'item')->get();

        /** @var \App\Models\Game\GameEquipment $slot */
        foreach ($equipmentSlots as $slot) {
            if ($slot->item) {
                $itemStats = $slot->item->stats ?? [];
                $bonus += (float) ($itemStats[$stat] ?? 0);

                // 词缀加成
                $affixes = $slot->item->affixes ?? [];
                foreach ($affixes as $affix) {
                    if (isset($affix[$stat])) {
                        $bonus += (float) $affix[$stat];
                    }
                }
            }
        }

        return $bonus;
    }

    /**
     * 获取升级所需经验
     */
    public function getExperienceToNextLevel(): int
    {
        $table = config('game.experience_table', []);
        $fallback = (int) config('game.experience_fallback_per_level', 5000);

        return $table[$this->level + 1] ?? ($this->level * $fallback);
    }

    /**
     * 获取当前等级总经验
     */
    public function getExperienceForCurrentLevel(): int
    {
        $table = config('game.experience_table', []);

        return $table[$this->level] ?? 0;
    }

    /**
     * 根据当前总经验重算等级（兜底：经验已达标但等级未更新的情况）
     * 在获取角色详情时调用，确保等级与经验一致。
     */
    public function reconcileLevelFromExperience(): bool
    {
        $levelsGained = 0;

        while ($this->experience >= $this->getExperienceToNextLevel()) {
            $this->level++;
            $this->skill_points += config('game.skill_points_per_level', 1);
            $this->stat_points += config('game.stat_points_per_level', 5);
            $levelsGained++;
        }

        if ($levelsGained > 0) {
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * 添加经验值（自动升级）
     */
    public function addExperience(int $amount): array
    {
        $this->experience += $amount;
        $levelsGained = 0;

        while ($this->experience >= $this->getExperienceToNextLevel()) {
            $this->level++;
            $this->skill_points += config('game.skill_points_per_level', 1);
            $this->stat_points += config('game.stat_points_per_level', 5);
            $levelsGained++;
        }

        $this->save();

        return [
            'experience_gained' => $amount,
            'levels_gained' => $levelsGained,
            'new_level' => $this->level,
            'total_experience' => $this->experience,
        ];
    }

    /**
     * 获取完整战斗属性
     */
    /**
     * @return array<string,mixed>
     */
    public function getCombatStats(): array
    {
        return [
            'max_hp' => $this->getMaxHp(),
            'max_mana' => $this->getMaxMana(),
            'attack' => $this->getAttack(),
            'defense' => $this->getDefense(),
            'crit_rate' => $this->getCritRate(),
            'crit_damage' => $this->getCritDamage(),
        ];
    }

    /**
     * 获取战斗属性明细（基础 + 装备），用于前端展示来源
     *
     * @return array<string, array{base: int|float, equipment: float, total: int|float}>
     */
    public function getCombatStatsBreakdown(): array
    {
        $equipAttack = $this->getEquipmentBonus('attack');
        $equipDefense = $this->getEquipmentBonus('defense');
        $equipCritRate = $this->getEquipmentBonus('crit_rate');
        $equipCritDamage = $this->getEquipmentBonus('crit_damage');

        $critConfig = config('game.combat.crit_rate', []);
        $critCap = (float) ($critConfig['cap'] ?? 0.10);
        $totalCritRate = min($critCap, $this->getBaseCritRate() + $equipCritRate);
        $totalCritDamage = $this->getBaseCritDamage() + $equipCritDamage;

        return [
            'attack' => [
                'base' => $this->getBaseAttack(),
                'equipment' => (float) $equipAttack,
                'total' => $this->getAttack(),
            ],
            'defense' => [
                'base' => $this->getBaseDefense(),
                'equipment' => (float) $equipDefense,
                'total' => $this->getDefense(),
            ],
            'crit_rate' => [
                'base' => round($this->getBaseCritRate(), 4),
                'equipment' => (float) $equipCritRate,
                'total' => round($totalCritRate, 4),
            ],
            'crit_damage' => [
                'base' => $this->getBaseCritDamage(),
                'equipment' => (float) $equipCritDamage,
                'total' => round($totalCritDamage, 4),
            ],
        ];
    }

    /**
     * 获取当前生命值（如果未设置则返回最大值）
     */
    public function getCurrentHp(): int
    {
        return $this->current_hp ?? $this->getMaxHp();
    }

    /**
     * 获取当前法力值（如果未设置则返回最大值）
     */
    public function getCurrentMana(): int
    {
        return $this->current_mana ?? $this->getMaxMana();
    }

    /**
     * 恢复生命值
     */
    public function restoreHp(int $amount): void
    {
        $maxHp = $this->getMaxHp();
        $currentHp = $this->getCurrentHp();
        $this->current_hp = min($maxHp, $currentHp + $amount);
        $this->save();
    }

    /**
     * 恢复法力值
     */
    public function restoreMana(int $amount): void
    {
        $maxMana = $this->getMaxMana();
        $currentMana = $this->getCurrentMana();
        $this->current_mana = min($maxMana, $currentMana + $amount);
        $this->save();
    }

    /**
     * 初始化HP/Mana（用于新角色或重置）
     * 只在字段为NULL时设置，不会覆盖已存在的值（包括0）
     */
    public function initializeHpMana(): void
    {
        $needsSave = false;

        // 只有当字段真正为NULL时才初始化（新角色）
        if ($this->current_hp === null && $this->getAttribute('current_hp') === null) {
            $this->current_hp = $this->getMaxHp();
            $needsSave = true;
        }

        if ($this->current_mana === null && $this->getAttribute('current_mana') === null) {
            $this->current_mana = $this->getMaxMana();
            $needsSave = true;
        }

        if ($needsSave) {
            $this->save();
        }
    }

    /**
     * 获取装备中的所有物品
     */
    public function getEquippedItems(): array
    {
        $equipped = [];
        $equipmentSlots = $this->equipment()->with('item.definition', 'item.gems')->get();

        /** @var \App\Models\Game\GameEquipment $slot */
        foreach ($equipmentSlots as $slot) {
            if ($slot->item) {
                $item = $slot->item;
                // 计算卖出价格（如果未设置）
                if (! isset($item->sell_price) || $item->sell_price === 0) {
                    $item->sell_price = $item->calculateSellPrice();
                }
                $equipped[$slot->slot] = $item;
            }
        }

        return $equipped;
    }

    /**
     * 发现一个物品
     */
    public function discoverItem(int $itemDefinitionId): void
    {
        $discovered = $this->discovered_items ?? [];
        if (! in_array($itemDefinitionId, $discovered)) {
            $discovered[] = $itemDefinitionId;
            $this->discovered_items = $discovered;
            $this->save();
        }
    }

    /**
     * 发现一个怪物
     */
    public function discoverMonster(int $monsterDefinitionId): void
    {
        $discovered = $this->discovered_monsters ?? [];
        if (! in_array($monsterDefinitionId, $discovered)) {
            $discovered[] = $monsterDefinitionId;
            $this->discovered_monsters = $discovered;
            $this->save();
        }
    }

    /**
     * 检查是否已发现物品
     */
    public function hasDiscoveredItem(int $itemDefinitionId): bool
    {
        $discovered = $this->discovered_items ?? [];

        return in_array($itemDefinitionId, $discovered);
    }

    /**
     * 检查是否已发现怪物
     */
    public function hasDiscoveredMonster(int $monsterDefinitionId): bool
    {
        $discovered = $this->discovered_monsters ?? [];

        return in_array($monsterDefinitionId, $discovered);
    }
}
