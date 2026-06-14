<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('game_characters')
            ->whereNotNull('combat_monsters')
            ->orderBy('id')
            ->chunkById(100, function ($characters): void {
                foreach ($characters as $character) {
                    $monsters = json_decode((string) $character->combat_monsters, true);
                    if (! is_array($monsters)) {
                        continue;
                    }

                    $rewardLayer = max(1, (int) ($character->current_map_id ?? 1));
                    $changed = false;
                    foreach ($monsters as &$monster) {
                        if (! is_array($monster)) {
                            continue;
                        }

                        if (($monster['reward_layer'] ?? null) !== $rewardLayer) {
                            $monster['reward_layer'] = $rewardLayer;
                            $changed = true;
                        }
                    }
                    unset($monster);

                    if ($changed) {
                        DB::table('game_characters')
                            ->where('id', $character->id)
                            ->update(['combat_monsters' => json_encode($monsters, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Keep cached combat monster reward layers; removing them would reintroduce old reward ambiguity.
    }
};
