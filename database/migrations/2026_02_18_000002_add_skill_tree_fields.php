<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 添加技能分支字段
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->string('branch', 32)->nullable()->comment('技能分支/流派：fire 火焰/ice 冰霜/lightning 闪电/warrior 力量/ranger 敏捷')->after('class_restriction');
            $table->unsignedTinyInteger('tier')->default(1)->comment('技能层级：1 基础/2 中级/3 高级')->after('branch');
            $table->unsignedBigInteger('prerequisite_skill_id')->nullable()->comment('前置技能 ID(学习此技能需要先学习前置技能)')->after('tier');
        });

        // 插入默认技能数据
        $skills = [
            // ====== 战士技能 ======
            // 力量分支 - 战士
            ['name' => '重击', 'description' => '基础物理攻击，造成 100%武器伤害', 'type' => 'active', 'class_restriction' => 'warrior', 'branch' => 'warrior', 'tier' => 1, 'prerequisite_skill_id' => null, 'mana_cost' => 0, 'cooldown' => 0, 'skill_points_cost' => 1, 'base_damage' => 100, 'target_type' => 'single', 'icon' => 'sword'],
            ['name' => '冲锋', 'description' => '冲向敌人造成 120%伤害', 'type' => 'active', 'class_restriction' => 'warrior', 'branch' => 'warrior', 'tier' => 2, 'prerequisite_skill_id' => 1, 'mana_cost' => 10, 'cooldown' => 3, 'skill_points_cost' => 1, 'base_damage' => 120, 'target_type' => 'single', 'icon' => 'zap'],
            ['name' => '旋风斩', 'description' => '旋转武器对所有敌人造成 150%伤害', 'type' => 'active', 'class_restriction' => 'warrior', 'branch' => 'warrior', 'tier' => 3, 'prerequisite_skill_id' => 2, 'mana_cost' => 30, 'cooldown' => 8, 'skill_points_cost' => 2, 'base_damage' => 150, 'target_type' => 'all', 'icon' => 'wind'],

            // ====== 法师技能 ======
            // 火焰分支 - 法师
            ['name' => '火球术', 'description' => '发射火球造成 100%魔法伤害', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'fire', 'tier' => 1, 'prerequisite_skill_id' => null, 'mana_cost' => 5, 'cooldown' => 0, 'skill_points_cost' => 1, 'base_damage' => 100, 'target_type' => 'single', 'icon' => 'flame'],
            ['name' => '燃烧', 'description' => '使敌人燃烧，每秒造成额外伤害', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'fire', 'tier' => 2, 'prerequisite_skill_id' => 4, 'mana_cost' => 15, 'cooldown' => 5, 'skill_points_cost' => 1, 'base_damage' => 80, 'target_type' => 'single', 'icon' => 'fire'],
            ['name' => '烈焰风暴', 'description' => '召唤火焰风暴攻击所有敌人', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'fire', 'tier' => 3, 'prerequisite_skill_id' => 5, 'mana_cost' => 50, 'cooldown' => 15, 'skill_points_cost' => 2, 'base_damage' => 200, 'target_type' => 'all', 'icon' => 'cloud-lightning'],

            // 冰霜分支 - 法师
            ['name' => '冰箭', 'description' => '发射冰箭造成 100%魔法伤害', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'ice', 'tier' => 1, 'prerequisite_skill_id' => null, 'mana_cost' => 5, 'cooldown' => 0, 'skill_points_cost' => 1, 'base_damage' => 100, 'target_type' => 'single', 'icon' => 'snowflake'],
            ['name' => '冰霜新星', 'description' => '减速周围所有敌人', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'ice', 'tier' => 2, 'prerequisite_skill_id' => 7, 'mana_cost' => 15, 'cooldown' => 8, 'skill_points_cost' => 1, 'base_damage' => 80, 'target_type' => 'all', 'icon' => 'disc'],
            ['name' => '冰封千里', 'description' => '冰冻所有敌人并造成大量伤害', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'ice', 'tier' => 3, 'prerequisite_skill_id' => 8, 'mana_cost' => 60, 'cooldown' => 20, 'skill_points_cost' => 2, 'base_damage' => 250, 'target_type' => 'all', 'icon' => 'cloud-snow'],

            // 闪电分支 - 法师
            ['name' => '雷击', 'description' => '召唤雷电打击单个敌人', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'lightning', 'tier' => 1, 'prerequisite_skill_id' => null, 'mana_cost' => 5, 'cooldown' => 0, 'skill_points_cost' => 1, 'base_damage' => 110, 'target_type' => 'single', 'icon' => 'zap'],
            ['name' => '连锁闪电', 'description' => '闪电在敌人之间弹跳', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'lightning', 'tier' => 2, 'prerequisite_skill_id' => 10, 'mana_cost' => 20, 'cooldown' => 6, 'skill_points_cost' => 1, 'base_damage' => 120, 'target_type' => 'all', 'icon' => 'git-branch'],
            ['name' => '雷霆万钧', 'description' => '召唤巨型雷电攻击所有敌人', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'lightning', 'tier' => 3, 'prerequisite_skill_id' => 11, 'mana_cost' => 60, 'cooldown' => 18, 'skill_points_cost' => 2, 'base_damage' => 280, 'target_type' => 'all', 'icon' => 'cloud-lightning'],

            // ====== 游侠技能 ======
            // 敏捷分支 - 游侠
            ['name' => '射击', 'description' => '远程物理攻击，造成 100%伤害', 'type' => 'active', 'class_restriction' => 'ranger', 'branch' => 'ranger', 'tier' => 1, 'prerequisite_skill_id' => null, 'mana_cost' => 0, 'cooldown' => 0, 'skill_points_cost' => 1, 'base_damage' => 100, 'target_type' => 'single', 'icon' => 'target'],
            ['name' => '多重射击', 'description' => '同时发射多支箭矢', 'type' => 'active', 'class_restriction' => 'ranger', 'branch' => 'ranger', 'tier' => 2, 'prerequisite_skill_id' => 10, 'mana_cost' => 15, 'cooldown' => 5, 'skill_points_cost' => 1, 'base_damage' => 150, 'target_type' => 'all', 'icon' => 'scan-eye'],
            ['name' => '疾风步', 'description' => '快速移动并提升下次攻击伤害', 'type' => 'active', 'class_restriction' => 'ranger', 'branch' => 'ranger', 'tier' => 3, 'prerequisite_skill_id' => 11, 'mana_cost' => 30, 'cooldown' => 10, 'skill_points_cost' => 2, 'base_damage' => 200, 'target_type' => 'single', 'icon' => 'feather'],

            // ====== 通用被动技能 ======
            ['name' => 'HP 强化', 'description' => '提升最大生命值+50', 'type' => 'passive', 'class_restriction' => 'all', 'branch' => 'passive', 'tier' => 1, 'prerequisite_skill_id' => null, 'mana_cost' => 0, 'cooldown' => 0, 'skill_points_cost' => 1, 'base_damage' => 0, 'target_type' => 'single', 'icon' => 'heart'],
            ['name' => 'MP 强化', 'description' => '提升最大法力值+30', 'type' => 'passive', 'class_restriction' => 'all', 'branch' => 'passive', 'tier' => 1, 'prerequisite_skill_id' => null, 'mana_cost' => 0, 'cooldown' => 0, 'skill_points_cost' => 1, 'base_damage' => 0, 'target_type' => 'single', 'icon' => 'zap'],
        ];

        foreach ($skills as $skill) {
            DB::table('game_skill_definitions')->insert([
                'name' => $skill['name'],
                'description' => $skill['description'],
                'type' => $skill['type'],
                'class_restriction' => $skill['class_restriction'],
                'branch' => $skill['branch'],
                'tier' => $skill['tier'],
                'prerequisite_skill_id' => $skill['prerequisite_skill_id'],
                'mana_cost' => $skill['mana_cost'],
                'cooldown' => $skill['cooldown'],
                'skill_points_cost' => $skill['skill_points_cost'],
                'base_damage' => $skill['base_damage'],
                'target_type' => $skill['target_type'],
                'icon' => $skill['icon'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->dropColumn(['branch', 'tier', 'prerequisite_skill_id']);
        });
    }
};
