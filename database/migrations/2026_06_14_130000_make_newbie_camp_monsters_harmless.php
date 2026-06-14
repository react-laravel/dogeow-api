<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Newbie camp is a safe training area: its monsters must never damage players.
     */
    public function up(): void
    {
        DB::table('game_monster_definitions')
            ->whereIn('name', ['猪', '鹿', '兔子'])
            ->update(['attack_base' => 0]);

        DB::table('game_characters')
            ->whereNotNull('combat_monsters')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $character): void {
                $monsters = json_decode((string) $character->combat_monsters, true);
                if (! is_array($monsters)) {
                    return;
                }

                $changed = false;
                foreach ($monsters as &$monster) {
                    if (isset($monster['name']) && in_array($monster['name'], ['猪', '鹿', '兔子'], true)) {
                        $monster['attack'] = 0;
                        $changed = true;
                    }
                }
                unset($monster);

                if ($changed) {
                    DB::table('game_characters')
                        ->where('id', $character->id)
                        ->update(['combat_monsters' => json_encode($monsters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally left empty: newbie-camp safety is a gameplay rule.
    }
};
