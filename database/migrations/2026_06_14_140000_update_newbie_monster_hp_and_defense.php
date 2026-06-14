<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MONSTER_STATS = [
        '兔子' => ['hp_base' => 1, 'defense_base' => 3],
        '鹿' => ['hp_base' => 2, 'defense_base' => 2],
        '猪' => ['hp_base' => 3, 'defense_base' => 1],
    ];

    public function up(): void
    {
        foreach (self::MONSTER_STATS as $name => $stats) {
            DB::table('game_monster_definitions')
                ->where('name', $name)
                ->update([
                    'hp_base' => $stats['hp_base'],
                    'defense_base' => $stats['defense_base'],
                    'attack_base' => 0,
                ]);
        }

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
                    if (! is_array($monster)) {
                        continue;
                    }

                    $name = $monster['name'] ?? null;
                    if (! is_string($name) || ! isset(self::MONSTER_STATS[$name])) {
                        continue;
                    }

                    $maxHp = self::MONSTER_STATS[$name]['hp_base'];
                    $currentHp = isset($monster['hp']) && is_numeric($monster['hp']) ? (int) $monster['hp'] : $maxHp;
                    $monster['hp'] = $currentHp > 0 ? min($currentHp, $maxHp) : 0;
                    $monster['max_hp'] = $maxHp;
                    $monster['attack'] = 0;
                    $monster['defense'] = self::MONSTER_STATS[$name]['defense_base'];
                    $changed = true;
                }
                unset($monster);

                if (! $changed) {
                    return;
                }

                $aliveMonsters = array_filter($monsters, 'is_array');
                $totalHp = (int) array_sum(array_column($aliveMonsters, 'hp'));
                $totalMaxHp = (int) array_sum(array_column($aliveMonsters, 'max_hp'));

                DB::table('game_characters')
                    ->where('id', $character->id)
                    ->update([
                        'combat_monsters' => json_encode($monsters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'combat_monster_hp' => $totalHp,
                        'combat_monster_max_hp' => $totalMaxHp,
                    ]);
            });
    }

    public function down(): void
    {
        // Balance migrations are not reverted automatically.
    }
};
