<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_combat_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('game_combat_logs', 'round_number')) {
                $table->dropColumn('round_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_combat_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('game_combat_logs', 'round_number')) {
                $table->unsignedSmallInteger('round_number')->nullable()->comment('回合数');
            }
        });
    }
};
