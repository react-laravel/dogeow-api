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
        Schema::table('game_characters', function (Blueprint $table) {
            $table->timestamp('combat_monsters_refreshed_at')->nullable()->after('combat_monsters')->comment('怪物刷新时间，用于定期刷新怪物属性');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn('combat_monsters_refreshed_at');
        });
    }
};
