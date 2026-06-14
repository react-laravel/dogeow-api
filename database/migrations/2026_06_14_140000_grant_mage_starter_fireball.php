<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fireball = DB::table('game_skill_definitions')
            ->where('class_restriction', 'mage')
            ->where('skill_line', 'mage_fireball')
            ->where('node_tier', 0)
            ->first();

        if (! $fireball) {
            return;
        }

        DB::table('game_skill_definitions')
            ->where('id', $fireball->id)
            ->update([
                'name' => '小火球',
                'description' => '单体 110% 攻击伤害',
            ]);

        DB::table('game_characters')
            ->where('class', 'mage')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $character) use ($fireball): void {
                $exists = DB::table('game_character_skills')
                    ->where('character_id', $character->id)
                    ->where('skill_id', $fireball->id)
                    ->exists();

                if ($exists) {
                    return;
                }

                $slotIndex = DB::table('game_character_skills')
                    ->where('character_id', $character->id)
                    ->where('slot_index', 0)
                    ->exists() ? null : 0;

                DB::table('game_character_skills')->insert([
                    'character_id' => $character->id,
                    'skill_id' => $fireball->id,
                    'level' => 1,
                    'slot_index' => $slotIndex,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Starter skill grants are a gameplay rule and are not removed on rollback.
    }
};
