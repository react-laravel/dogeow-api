<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if running on MySQL
     */
    private function isMySQL(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    public function up(): void
    {
        if (! $this->isMySQL()) {
            return;
        }

        // 修改 enum 值：amulet -> ring
        DB::statement("ALTER TABLE game_equipment MODIFY COLUMN slot ENUM('weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring') NOT NULL COMMENT '装备槽位'");
    }

    public function down(): void
    {
        if (! $this->isMySQL()) {
            return;
        }

        // 恢复原来的 enum 值
        DB::statement("ALTER TABLE game_equipment MODIFY COLUMN slot ENUM('weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring', 'amulet') NOT NULL COMMENT '装备槽位'");
    }
};
