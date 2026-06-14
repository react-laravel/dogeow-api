<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_combat_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('game_combat_logs', 'is_crit')) {
                $table->boolean('is_crit')->nullable()->after('monsters_killed_count')->comment('本回合是否暴击');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_combat_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('game_combat_logs', 'is_crit')) {
                $table->dropColumn('is_crit');
            }
        });
    }
};
