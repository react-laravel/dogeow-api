<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 新库由 GameSeeder 写入标准数据；此迁移只修复已有游戏数据。
        if (
            ! DB::table('game_monster_definitions')->exists()
            && ! DB::table('game_map_definitions')->exists()
        ) {
            return;
        }

        $now = now();
        $monsters = require database_path('seeders/Game/Data/monsters.php');
        $maps = require database_path('seeders/Game/Data/maps.php');

        $monsterIdsByName = [];

        foreach ($monsters as $monster) {
            $assetKey = $monster['asset_key'] ?? ('monster_' . strtolower(str_replace(' ', '_', $monster['name'])));
            unset($monster['asset_key']);

            $payload = array_merge($monster, [
                'drop_table' => json_encode($monster['drop_table'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'icon' => $assetKey . '.png',
                'is_active' => true,
                'updated_at' => $now,
            ]);

            $existing = DB::table('game_monster_definitions')
                ->where('name', $monster['name'])
                ->first();

            if ($existing !== null) {
                DB::table('game_monster_definitions')
                    ->where('id', $existing->id)
                    ->update($payload);

                $monsterIdsByName[$monster['name']] = (int) $existing->id;

                continue;
            }

            $monsterIdsByName[$monster['name']] = (int) DB::table('game_monster_definitions')
                ->insertGetId(array_merge($payload, [
                    'hp_per_level' => 0,
                    'attack_per_level' => 0,
                    'defense_per_level' => 0,
                    'experience_per_level' => 0,
                    'created_at' => $now,
                ]));
        }

        foreach ($maps as $index => $map) {
            $assetKey = $map['asset_key'] ?? ('map_' . ($index + 1));
            $monsterIds = array_values(array_filter(array_map(
                fn (int $monsterOrder): ?int => $monsterIdsByName[$monsters[$monsterOrder - 1]['name']] ?? null,
                array_map('intval', (array) ($map['monster_ids'] ?? []))
            )));

            unset($map['asset_key']);

            $payload = array_merge($map, [
                'monster_ids' => json_encode($monsterIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'background' => $assetKey . '.jpg',
                'is_active' => true,
                'updated_at' => $now,
            ]);

            $existing = DB::table('game_map_definitions')
                ->where('name', $map['name'])
                ->where('act', $map['act'])
                ->first();

            if ($existing !== null) {
                DB::table('game_map_definitions')
                    ->where('id', $existing->id)
                    ->update($payload);

                continue;
            }

            DB::table('game_map_definitions')->insert(array_merge($payload, [
                'created_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        // 平衡与数据补齐迁移不自动回滚，避免破坏线上已有战斗数据。
    }
};
