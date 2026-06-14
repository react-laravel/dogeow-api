<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('game_skill_definitions')
            ->where('class_restriction', 'mage')
            ->where('name', '火球术')
            ->update([
                'name' => '小火球',
                'description' => '单体 110% 攻击伤害',
            ]);
    }

    public function down(): void
    {
        // Keep the unified starter skill name.
    }
};
