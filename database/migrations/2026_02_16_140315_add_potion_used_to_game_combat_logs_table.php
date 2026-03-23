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
            $table->json('potion_used')->nullable()->after('skills_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_combat_logs', function (Blueprint $table) {
            $table->dropColumn('potion_used');
        });
    }
};
