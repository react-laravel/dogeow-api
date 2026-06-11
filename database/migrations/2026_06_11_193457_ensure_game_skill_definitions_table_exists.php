<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function isMySQL(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function setTableComment(string $table, string $comment): void
    {
        if ($this->isMySQL()) {
            DB::statement("ALTER TABLE {$table} COMMENT = '{$comment}'");
        }
    }

    public function up(): void
    {
        if (Schema::hasTable('game_skill_definitions')) {
            return;
        }

        Schema::create('game_skill_definitions', function (Blueprint $table) {
            $table->id()->comment('技能定义 ID');
            $table->string('name', 64)->comment('技能名称');
            $table->text('description')->nullable()->comment('技能描述');
            $table->enum('type', ['active', 'passive'])->default('active')->comment('技能类型：active 主动/passive 被动');
            $table->enum('class_restriction', ['warrior', 'mage', 'ranger', 'all'])->default('all')->comment('职业限制');
            $table->string('branch', 32)->nullable()->comment('技能分支/流派：fire/ice/lightning/warrior/ranger/passive');
            $table->unsignedTinyInteger('tier')->default(1)->comment('技能层级：1 基础/2 中级/3 高级');
            $table->unsignedBigInteger('prerequisite_skill_id')->nullable()->comment('前置技能 ID');
            $table->string('prerequisite_effect_key', 64)->nullable()->comment('前置技能效果键');
            $table->unsignedSmallInteger('mana_cost')->default(0)->comment('法力消耗');
            $table->unsignedTinyInteger('cooldown')->default(0)->comment('冷却时间(秒)');
            $table->unsignedTinyInteger('skill_points_cost')->default(1)->comment('学习消耗技能点数');
            $table->unsignedTinyInteger('max_level')->default(10)->comment('最大等级');
            $table->unsignedSmallInteger('base_damage')->default(10)->comment('基础伤害');
            $table->unsignedSmallInteger('damage_per_level')->default(5)->comment('每级伤害加成');
            $table->unsignedSmallInteger('mana_cost_per_level')->default(0)->comment('每级法力消耗加成');
            $table->string('icon', 64)->nullable()->comment('图标');
            $table->string('effect_key', 32)->nullable()->comment('前端技能特效标识');
            $table->text('icon_prompt')->nullable()->comment('AI生成技能图标提示词');
            $table->json('effects')->nullable()->comment('效果(JSON)');
            $table->string('target_type', 16)->default('single')->comment('目标类型：single 单体/all 全体');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
        });
        $this->setTableComment('game_skill_definitions', '技能定义表');
    }

    public function down(): void
    {
        // 修复性迁移：不在回滚时删除表，避免误删已有数据
    }
};
