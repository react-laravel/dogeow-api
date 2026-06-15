<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recalculate monster HP against the current small-number layer curve.
     */
    public function up(): void
    {
        DB::table('game_monster_definitions')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $monster): void {
                $level = max(1, (int) ($monster->level ?? 1));
                $type = (string) ($monster->type ?? 'normal');
                $archetypeIndex = ($monster->id - 1) % 3;
                $baseHp = match ($archetypeIndex) {
                    0 => 3 + ($level - 1) * 6,
                    1 => 2 + ($level - 1) * 4,
                    default => 1 + ($level - 1) * 2,
                };
                $hpMultiplier = match ($type) {
                    'boss' => 4.0,
                    'elite' => 2.2,
                    default => 1.0,
                };

                DB::table('game_monster_definitions')
                    ->where('id', $monster->id)
                    ->update(['hp_base' => max(1, (int) round($baseHp * $hpMultiplier))]);
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: previous HP values came from multiple
        // legacy balance passes and should not be restored blindly.
    }
};
