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
            $table->json('discovered_items')->nullable()->comment('已发现的物品 ID 数组')->after('combat_monsters');
            $table->json('discovered_monsters')->nullable()->comment('已发现的怪物 ID 数组')->after('discovered_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn(['discovered_items', 'discovered_monsters']);
        });
    }
};
