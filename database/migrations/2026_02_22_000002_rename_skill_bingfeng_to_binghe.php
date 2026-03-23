<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 将技能「冰封千里」更名为「冰河世纪」并设置 effect_key 为 ice-age.
     */
    public function up(): void
    {
        DB::table('game_skill_definitions')
            ->where('name', '冰封千里')
            ->update([
                'name' => '冰河世纪',
                'effect_key' => 'ice-age',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('game_skill_definitions')
            ->where('name', '冰河世纪')
            ->update([
                'name' => '冰封千里',
                'effect_key' => 'ice-arrow',
                'updated_at' => now(),
            ]);
    }
};
