<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_combat_logs', function (Blueprint $table) {
            // 角色属性
            $table->unsignedTinyInteger('character_level')->nullable()->after('potion_used')->comment('角色等级');
            $table->string('character_class', 20)->nullable()->after('character_level')->comment('角色职业');
            $table->unsignedInteger('character_attack')->nullable()->after('character_class')->comment('角色攻击力');
            $table->unsignedInteger('character_defense')->nullable()->after('character_attack')->comment('角色防御力');
            $table->decimal('character_crit_rate', 5, 2)->nullable()->after('character_defense')->comment('角色暴击率(%)');
            $table->decimal('character_crit_damage', 5, 2)->nullable()->after('character_crit_rate')->comment('角色暴击伤害(倍数)');

            // 怪物属性
            $table->unsignedTinyInteger('monster_level')->nullable()->after('character_crit_damage')->comment('怪物等级');
            $table->unsignedInteger('monster_hp')->nullable()->after('monster_level')->comment('怪物当前血量');
            $table->unsignedInteger('monster_max_hp')->nullable()->after('monster_hp')->comment('怪物最大血量');
            $table->unsignedInteger('monster_attack')->nullable()->after('monster_max_hp')->comment('怪物攻击力');
            $table->unsignedInteger('monster_defense')->nullable()->after('monster_attack')->comment('怪物防御力');
            $table->unsignedInteger('monster_experience')->nullable()->after('monster_defense')->comment('怪物经验值');
            $table->unsignedInteger('monster_copper')->nullable()->after('monster_experience')->comment('怪物铜币掉落');

            // 伤害详情
            $table->unsignedInteger('base_attack_damage')->nullable()->after('monster_copper')->comment('基础/技能伤害');
            $table->unsignedInteger('skill_damage')->nullable()->after('base_attack_damage')->comment('技能额外伤害');
            $table->unsignedInteger('crit_damage')->nullable()->after('skill_damage')->comment('暴击额外伤害');
            $table->unsignedInteger('aoe_damage')->nullable()->after('crit_damage')->comment('AOE 伤害减免');
            $table->unsignedInteger('total_damage_to_monsters')->nullable()->after('aoe_damage')->comment('本回合总伤害');
            $table->decimal('monster_defense_reduction', 5, 2)->nullable()->after('total_damage_to_monsters')->comment('怪物防御减伤(%)');
            $table->unsignedInteger('monster_counter_damage')->nullable()->after('monster_defense_reduction')->comment('怪物反击伤害');

            // 战斗详情
            $table->unsignedSmallInteger('round_number')->nullable()->after('monster_counter_damage')->comment('回合数');
            $table->unsignedTinyInteger('monsters_alive_count')->nullable()->after('round_number')->comment('存活怪物数');
            $table->unsignedTinyInteger('monsters_killed_count')->nullable()->after('monsters_alive_count')->comment('杀死怪物数');

            // 难度相关
            $table->unsignedTinyInteger('difficulty_tier')->nullable()->after('monsters_killed_count')->comment('难度等级');
            $table->decimal('difficulty_multiplier', 5, 2)->nullable()->after('difficulty_tier')->comment('难度系数');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_combat_logs', function (Blueprint $table) {
            $table->dropColumn([
                'character_level',
                'character_class',
                'character_attack',
                'character_defense',
                'character_crit_rate',
                'character_crit_damage',
                'monster_level',
                'monster_hp',
                'monster_max_hp',
                'monster_attack',
                'monster_defense',
                'monster_experience',
                'monster_copper',
                'base_attack_damage',
                'skill_damage',
                'crit_damage',
                'aoe_damage',
                'total_damage_to_monsters',
                'monster_defense_reduction',
                'monster_counter_damage',
                'round_number',
                'monsters_alive_count',
                'monsters_killed_count',
                'difficulty_tier',
                'difficulty_multiplier',
            ]);
        });
    }
};
