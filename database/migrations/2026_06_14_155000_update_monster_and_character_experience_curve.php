<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('game_monster_definitions')) {
            return;
        }

        DB::table('game_monster_definitions')
            ->orderBy('id')
            ->select(['id', 'level'])
            ->chunk(100, function ($monsters): void {
                foreach ($monsters as $monster) {
                    $level = max(1, (int) $monster->level);

                    DB::table('game_monster_definitions')
                        ->where('id', $monster->id)
                        ->update([
                            'experience_base' => $level ** 2,
                            'experience_per_level' => 0,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // 经验曲线迁移不自动回滚，避免覆盖线上已调整的平衡数据。
    }
};
