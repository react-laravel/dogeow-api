<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('game_skill_definitions')
            ->where('class_restriction', 'mage')
            ->where('skill_line', 'mage_fireball')
            ->where('node_tier', 0)
            ->update([
                'name' => '小火球',
                'description' => '单体 2 点火焰伤害',
                'mana_cost' => 8,
                'base_damage' => 2,
                'is_active' => true,
            ]);
    }

    public function down(): void
    {
        // 技能平衡迁移不自动回滚，避免线上角色技能表现来回跳变。
    }
};
