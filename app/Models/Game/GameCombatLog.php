<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property mixed $id
 * @property mixed $character_id
 * @property mixed $map_id
 * @property mixed $monster_id
 * @property mixed $damage_dealt
 * @property mixed $damage_taken
 * @property mixed $victory
 * @property mixed $loot_dropped
 * @property mixed $experience_gained
 * @property mixed $copper_gained
 * @property mixed $duration_seconds
 * @property mixed $skills_used
 */
class GameCombatLog extends Model
{
    protected $fillable = [
        'character_id',
        'map_id',
        'monster_id',
        'damage_dealt',
        'damage_taken',
        'victory',
        'loot_dropped',
        'experience_gained',
        'copper_gained',
        'duration_seconds',
        'skills_used',
        'potion_used',
        // 角色属性
        'character_level',
        'character_class',
        'character_attack',
        'character_defense',
        'character_crit_rate',
        'character_crit_damage',
        // 怪物属性
        'monster_level',
        'monster_hp',
        'monster_max_hp',
        'monster_attack',
        'monster_defense',
        'monster_experience',
        'monster_copper',
        // 伤害详情
        'base_attack_damage',
        'skill_damage',
        'crit_damage',
        'aoe_damage',
        'total_damage_to_monsters',
        'monster_defense_reduction',
        'monster_counter_damage',
        // 战斗详情
        'round_number',
        'monsters_alive_count',
        'monsters_killed_count',
        // 难度相关
        'difficulty_tier',
        'difficulty_multiplier',
    ];

    protected function casts(): array
    {
        return [
            'victory' => 'boolean',
            'loot_dropped' => 'array',
            'skills_used' => 'array',
            'potion_used' => 'array',
        ];
    }

    /**
     * 获取所属角色
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'character_id');
    }

    /**
     * 获取地图
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(GameMapDefinition::class, 'map_id');
    }

    /**
     * 获取怪物
     */
    public function monster(): BelongsTo
    {
        return $this->belongsTo(GameMonsterDefinition::class, 'monster_id');
    }
}
