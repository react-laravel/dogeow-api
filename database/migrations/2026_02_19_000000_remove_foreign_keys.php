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

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->isMySQL()) {
            return;
        }

        // 移除所有 game_ 表的外键约束
        $foreignKeys = DB::select("
            SELECT TABLE_NAME, CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND TABLE_NAME LIKE 'game_%'
        ");

        foreach ($foreignKeys as $fk) {
            DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 不需要恢复，外键在业务稳定后不需要重新添加
    }
};
