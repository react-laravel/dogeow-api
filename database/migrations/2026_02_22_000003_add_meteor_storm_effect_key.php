<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 为烈焰风暴技能设置 effect_key
     */
    public function up(): void
    {
        $affected = DB::table('game_skill_definitions')
            ->where('name', '烈焰风暴')
            ->update(['effect_key' => 'meteor-storm']);

        if ($affected === 0) {
            // 如果技能不存在，则插入
            DB::table('game_skill_definitions')->insert([
                'name' => '烈焰风暴',
                'description' => '召唤火焰风暴攻击所有敌人',
                'type' => 'active',
                'class_restriction' => 'mage',
                'branch' => 'fire',
                'tier' => 3,
                'prerequisite_skill_id' => 5,
                'mana_cost' => 50,
                'cooldown' => 15,
                'skill_points_cost' => 2,
                'max_level' => 10,
                'base_damage' => 200,
                'damage_per_level' => 5,
                'mana_cost_per_level' => 0,
                'icon' => 'cloud-lightning',
                'effect_key' => 'meteor-storm',
                'target_type' => 'all',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('game_skill_definitions')
            ->where('name', '烈焰风暴')
            ->update(['effect_key' => null]);
    }
};
