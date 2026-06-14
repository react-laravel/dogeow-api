<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add newbie-camp training monsters (猪/鹿/兔子) and point 新手营地 at them.
     * Existing monsters (小野猪/野狼等) stay in the table for other maps.
     */
    public function up(): void
    {
        // Fresh installs get canonical data from GameSeeder; only backfill legacy databases.
        if (! DB::table('game_monster_definitions')->where('name', '小野猪')->exists()) {
            return;
        }

        $now = now();
        $monsterIds = [];

        foreach ($this->newbieMonsters() as $monster) {
            $existing = DB::table('game_monster_definitions')
                ->where('name', $monster['name'])
                ->first();

            if ($existing !== null) {
                DB::table('game_monster_definitions')
                    ->where('id', $existing->id)
                    ->update(array_merge($monster, ['updated_at' => $now]));
                $monsterIds[] = (int) $existing->id;

                continue;
            }

            $monsterIds[] = (int) DB::table('game_monster_definitions')->insertGetId(array_merge(
                $monster,
                [
                    'hp_per_level' => 0,
                    'attack_per_level' => 0,
                    'defense_per_level' => 0,
                    'experience_per_level' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ));
        }

        DB::table('game_map_definitions')
            ->where('name', '新手营地')
            ->where('act', 1)
            ->update([
                'monster_ids' => json_encode($monsterIds),
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $now = now();
        $legacyMonsterIds = DB::table('game_monster_definitions')
            ->whereIn('name', ['小野猪', '野狼'])
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($legacyMonsterIds !== []) {
            DB::table('game_map_definitions')
                ->where('name', '新手营地')
                ->where('act', 1)
                ->update([
                    'monster_ids' => json_encode($legacyMonsterIds),
                    'updated_at' => $now,
                ]);
        }

        DB::table('game_monster_definitions')
            ->whereIn('name', ['猪', '鹿', '兔子'])
            ->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newbieMonsters(): array
    {
        $dropTable = json_encode([
            'item_chance' => 0.35,
            'potion_chance' => 0.35,
            'item_types' => ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'ring', 'amulet', 'belt'],
        ]);

        return [
            [
                'name' => '猪',
                'type' => 'normal',
                'level' => 1,
                'hp_base' => 3,
                'hp_per_level' => 0,
                'attack_base' => 1,
                'attack_per_level' => 0,
                'defense_base' => 1,
                'defense_per_level' => 0,
                'experience_base' => 8,
                'experience_per_level' => 0,
                'drop_table' => $dropTable,
                'icon' => 'training-pig.png',
                'icon_prompt' => 'RPG monster portrait, cute pink piglet, soft fur, friendly training monster, gentle expression, game character art, square composition, dark background',
            ],
            [
                'name' => '鹿',
                'type' => 'normal',
                'level' => 1,
                'hp_base' => 2,
                'hp_per_level' => 0,
                'attack_base' => 1,
                'attack_per_level' => 0,
                'defense_base' => 2,
                'defense_per_level' => 0,
                'experience_base' => 6,
                'experience_per_level' => 0,
                'drop_table' => $dropTable,
                'icon' => 'training-deer.png',
                'icon_prompt' => 'RPG monster portrait, young fawn deer, spotted coat, gentle eyes, peaceful training monster, soft lighting, game character art, square composition, dark background',
            ],
            [
                'name' => '兔子',
                'type' => 'normal',
                'level' => 1,
                'hp_base' => 1,
                'hp_per_level' => 0,
                'attack_base' => 0,
                'attack_per_level' => 0,
                'defense_base' => 3,
                'defense_per_level' => 0,
                'experience_base' => 4,
                'experience_per_level' => 0,
                'drop_table' => $dropTable,
                'icon' => 'training-rabbit.png',
                'icon_prompt' => 'RPG monster portrait, fluffy white rabbit, carrot nearby, adorable harmless training monster, soft lighting, game character art, square composition, dark background',
            ],
        ];
    }
};
