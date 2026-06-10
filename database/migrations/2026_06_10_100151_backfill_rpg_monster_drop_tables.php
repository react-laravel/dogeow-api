<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill canonical RPG monster drop tables so combat can actually award
     * equipment and potions on existing installations.
     */
    public function up(): void
    {
        DB::table('game_monster_definitions')
            ->whereNull('drop_table')
            ->orderBy('id')
            ->get(['id', 'type', 'level'])
            ->each(function (object $monster): void {
                DB::table('game_monster_definitions')
                    ->where('id', $monster->id)
                    ->update([
                        'drop_table' => json_encode($this->defaultDropTable((string) $monster->type, (int) $monster->level)),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('game_monster_definitions')
            ->orderBy('id')
            ->get(['id', 'type', 'level', 'drop_table'])
            ->each(function (object $monster): void {
                $dropTable = is_string($monster->drop_table)
                    ? json_decode($monster->drop_table, true)
                    : $monster->drop_table;

                if ($dropTable === $this->defaultDropTable((string) $monster->type, (int) $monster->level)) {
                    DB::table('game_monster_definitions')
                        ->where('id', $monster->id)
                        ->update(['drop_table' => null]);
                }
            });
    }

    /**
     * @return array{item_chance: float, potion_chance: float, item_types: array<int, string>}
     */
    private function defaultDropTable(string $type, int $level): array
    {
        $itemTypes = ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'ring', 'amulet', 'belt'];

        if ($level <= 2) {
            return [
                'item_chance' => 0.35,
                'potion_chance' => 0.35,
                'item_types' => $itemTypes,
            ];
        }

        return match ($type) {
            'boss' => [
                'item_chance' => 0.5,
                'potion_chance' => 0.3,
                'item_types' => $itemTypes,
            ],
            'elite' => [
                'item_chance' => 0.3,
                'potion_chance' => 0.25,
                'item_types' => $itemTypes,
            ],
            default => [
                'item_chance' => 0.2,
                'potion_chance' => 0.2,
                'item_types' => $itemTypes,
            ],
        };
    }
};
