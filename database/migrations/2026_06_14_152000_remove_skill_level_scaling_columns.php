<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['max_level', 'damage_per_level', 'mana_cost_per_level'],
                fn (string $column): bool => Schema::hasColumn('game_skill_definitions', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('game_character_skills', function (Blueprint $table): void {
            if (Schema::hasColumn('game_character_skills', 'level')) {
                $table->dropColumn('level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table): void {
            if (! Schema::hasColumn('game_skill_definitions', 'max_level')) {
                $table->unsignedTinyInteger('max_level')->default(1)->comment('最大等级');
            }

            if (! Schema::hasColumn('game_skill_definitions', 'damage_per_level')) {
                $table->unsignedSmallInteger('damage_per_level')->default(0)->comment('每级伤害加成');
            }

            if (! Schema::hasColumn('game_skill_definitions', 'mana_cost_per_level')) {
                $table->unsignedSmallInteger('mana_cost_per_level')->default(0)->comment('每级法力消耗加成');
            }
        });

        Schema::table('game_character_skills', function (Blueprint $table): void {
            if (! Schema::hasColumn('game_character_skills', 'level')) {
                $table->unsignedMediumInteger('level')->default(1)->comment('技能等级');
            }
        });
    }
};
