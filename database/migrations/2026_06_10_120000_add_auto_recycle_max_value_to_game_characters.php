<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->unsignedInteger('auto_recycle_max_value')
                ->nullable()
                ->after('mp_potion_threshold')
                ->comment('自动回收价值上限(铜)，单价≤此值的背包装备将自动出售');
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropColumn('auto_recycle_max_value');
        });
    }
};
