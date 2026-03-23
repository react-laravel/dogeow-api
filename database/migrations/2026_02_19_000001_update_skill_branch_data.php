<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 更新现有技能数据，添加 branch 字段
     */
    public function up(): void
    {
        // ====== 战士技能 ======
        DB::table('game_skill_definitions')->where('name', '重击')->update(['branch' => 'warrior', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', '战吼')->update(['branch' => 'warrior', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', '铁壁')->update(['branch' => 'passive', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', '冲锋')->update(['branch' => 'warrior', 'tier' => 2, 'prerequisite_skill_id' => 1]);
        DB::table('game_skill_definitions')->where('name', '旋风斩')->update(['branch' => 'warrior', 'tier' => 2, 'prerequisite_skill_id' => 1]);
        DB::table('game_skill_definitions')->where('name', '狂暴')->update(['branch' => 'warrior', 'tier' => 2]);
        DB::table('game_skill_definitions')->where('name', '钢铁之躯')->update(['branch' => 'passive', 'tier' => 2]);
        DB::table('game_skill_definitions')->where('name', '斩杀')->update(['branch' => 'warrior', 'tier' => 3]);

        // ====== 法师火系技能 ======
        DB::table('game_skill_definitions')->where('name', '火球术')->update(['branch' => 'fire', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', '燃烧')->update(['branch' => 'fire', 'tier' => 2]);
        DB::table('game_skill_definitions')->where('name', '烈焰风暴')->update(['branch' => 'fire', 'tier' => 3]);
        DB::table('game_skill_definitions')->where('name', '陨石术')->update(['branch' => 'fire', 'tier' => 3]);

        // ====== 法师冰系技能 ======
        DB::table('game_skill_definitions')->where('name', '冰箭')->update(['branch' => 'ice', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', '冰霜新星')->update(['branch' => 'ice', 'tier' => 2]);
        DB::table('game_skill_definitions')->where('name', '冰封千里')->update(['branch' => 'ice', 'tier' => 3]);

        // ====== 法师闪电系技能 ======
        DB::table('game_skill_definitions')->where('name', '雷击')->update(['branch' => 'lightning', 'tier' => 1]);

        // ====== 法师奥术/其他技能 ======
        DB::table('game_skill_definitions')->where('name', '魔力涌动')->update(['branch' => 'passive', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', '魔法护盾')->update(['branch' => 'arcane', 'tier' => 2]);
        DB::table('game_skill_definitions')->where('name', '奥术智慧')->update(['branch' => 'passive', 'tier' => 2]);

        // ====== 游侠技能 ======
        DB::table('game_skill_definitions')->where('name', '射击')->update(['branch' => 'ranger', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', '穿刺射击')->update(['branch' => 'ranger', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', '多重射击')->update(['branch' => 'ranger', 'tier' => 2]);
        DB::table('game_skill_definitions')->where('name', '毒箭')->update(['branch' => 'poison', 'tier' => 2]);
        DB::table('game_skill_definitions')->where('name', '闪避')->update(['branch' => 'ranger', 'tier' => 2]);
        DB::table('game_skill_definitions')->where('name', '疾风步')->update(['branch' => 'ranger', 'tier' => 3]);
        DB::table('game_skill_definitions')->where('name', '箭雨')->update(['branch' => 'ranger', 'tier' => 3]);
        DB::table('game_skill_definitions')->where('name', '暗影步')->update(['branch' => 'ranger', 'tier' => 3]);

        // ====== 游侠被动技能 ======
        DB::table('game_skill_definitions')->where('name', '鹰眼')->update(['branch' => 'passive', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', '致命瞄准')->update(['branch' => 'passive', 'tier' => 2]);

        // ====== 通用被动技能 ======
        DB::table('game_skill_definitions')->where('name', 'HP 强化')->update(['branch' => 'passive', 'tier' => 1]);
        DB::table('game_skill_definitions')->where('name', 'MP 强化')->update(['branch' => 'passive', 'tier' => 1]);

        // ====== 添加闪电系技能 ======
        $lightningSkills = [
            ['name' => '雷击', 'description' => '召唤雷电打击单个敌人', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'lightning', 'tier' => 1, 'mana_cost' => 5, 'cooldown' => 0, 'skill_points_cost' => 1, 'base_damage' => 110, 'target_type' => 'single', 'icon' => 'zap'],
            ['name' => '连锁闪电', 'description' => '闪电在敌人之间弹跳', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'lightning', 'tier' => 2, 'mana_cost' => 20, 'cooldown' => 6, 'skill_points_cost' => 1, 'base_damage' => 120, 'target_type' => 'all', 'icon' => 'git-branch'],
            ['name' => '雷霆万钧', 'description' => '召唤巨型雷电攻击所有敌人', 'type' => 'active', 'class_restriction' => 'mage', 'branch' => 'lightning', 'tier' => 3, 'mana_cost' => 60, 'cooldown' => 18, 'skill_points_cost' => 2, 'base_damage' => 280, 'target_type' => 'all', 'icon' => 'cloud-lightning'],
        ];

        $lightningIds = [];
        foreach ($lightningSkills as $skill) {
            $id = DB::table('game_skill_definitions')->insertGetId([
                'name' => $skill['name'],
                'description' => $skill['description'],
                'type' => $skill['type'],
                'class_restriction' => $skill['class_restriction'],
                'branch' => $skill['branch'],
                'tier' => $skill['tier'],
                'prerequisite_skill_id' => null,
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
            $lightningIds[$skill['name']] = $id;
        }

        // 更新闪电系 2 级和 3 级技能的前置技能
        if (isset($lightningIds['连锁闪电']) && isset($lightningIds['雷击'])) {
            DB::table('game_skill_definitions')
                ->where('name', '连锁闪电')
                ->update(['prerequisite_skill_id' => $lightningIds['雷击']]);
        }
        if (isset($lightningIds['雷霆万钧']) && isset($lightningIds['连锁闪电'])) {
            DB::table('game_skill_definitions')
                ->where('name', '雷霆万钧')
                ->update(['prerequisite_skill_id' => $lightningIds['连锁闪电']]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('game_skill_definitions')->where('branch', 'lightning')->delete();
    }
};
