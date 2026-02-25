<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $id
 * @property mixed $damage
 * @property mixed $mana_cost
 * @property mixed $cooldown
 * @property mixed $type
 * @property mixed $target_type
 * @property mixed $base_damage
 * @property mixed $damage_per_level
 */
class GameSkillDefinition extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'class_restriction',
        'damage',
        'mana_cost',
        'cooldown',
        'icon',
        'effect_key',
        'effects',
        'target_type',
        'is_active',
        'max_level',
        'base_damage',
        'damage_per_level',
        'mana_cost_per_level',
        'skill_points_cost',
        'branch',
        'tier',
        'prerequisite_skill_id',
    ];

    protected function casts(): array
    {
        return [
            'effects' => 'array',
            'is_active' => 'boolean',
            'cooldown' => 'float',
            'max_level' => 'integer',
            'base_damage' => 'integer',
            'damage_per_level' => 'integer',
            'mana_cost_per_level' => 'integer',
        ];
    }

    public const TYPES = ['active', 'passive'];

    public const CLASS_RESTRICTIONS = ['warrior', 'mage', 'ranger', 'all'];

    /**
     * 检查职业是否可以使用该技能
     */
    public function canLearnByClass(string $class): bool
    {
        return $this->class_restriction === 'all' || $this->class_restriction === $class;
    }
}
