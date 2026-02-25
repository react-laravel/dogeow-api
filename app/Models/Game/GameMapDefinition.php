<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMapDefinition extends Model
{
    protected $fillable = [
        'name',
        'act',
        'monster_ids',
        'background',
        'description',
        'is_active',
    ];

    protected $hidden = [
        'min_level',
        'max_level',
    ];

    protected function casts(): array
    {
        return [
            'monster_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 获取地图进度记录
     */
    public function characterMaps(): HasMany
    {
        return $this->hasMany(GameCharacterMap::class, 'map_id');
    }

    /**
     * 获取地图中的怪物列表
     */
    /**
     * 获取地图中的怪物列表
     *
     * @return array<int, GameMonsterDefinition>
     */
    public function getMonsters(): array
    {
        $ids = $this->monster_ids;
        if (empty($ids) || ! is_array($ids)) {
            return [];
        }

        $ids = array_map('intval', array_values($ids));
        $ids = array_filter($ids, fn ($id) => $id > 0);
        if (empty($ids)) {
            return [];
        }

        return GameMonsterDefinition::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->where('is_active', true)
            ->get()
            ->all();
    }

    /**
     * 检查角色等级是否可以进入（无等级限制）
     */
    public function canEnter(int $level): bool
    {
        return true;
    }

    /**
     * 获取推荐等级描述（无等级限制）
     */
    public function getLevelRangeText(): string
    {
        return '无等级限制';
    }
}
