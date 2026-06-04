<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $slot_index
 * @property int|null $level
 * @property GameSkillDefinition|null $skill
 */
class GameCharacterSkill extends Model
{
    protected $fillable = [
        'character_id',
        'skill_id',
    ];

    /**
     * 获取所属角色
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'character_id');
    }

    /**
     * 获取技能定义
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(GameSkillDefinition::class, 'skill_id');
    }

    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
            'skill_id' => 'integer',
            'level' => 'integer',
            'slot_index' => 'integer',
        ];
    }
}
