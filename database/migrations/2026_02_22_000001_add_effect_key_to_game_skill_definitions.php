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
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->string('effect_key', 32)->nullable()->after('icon')->comment('前端技能特效标识：meteor-storm/fireball/ice-arrow/blackhole/heal/lightning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_skill_definitions', function (Blueprint $table) {
            $table->dropColumn('effect_key');
        });
    }
};
