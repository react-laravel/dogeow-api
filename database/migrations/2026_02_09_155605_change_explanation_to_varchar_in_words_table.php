<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run on MySQL - SQLite doesn't support JSON functions
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // 1. 添加临时字段用于存储提取的中文释义
        Schema::table('words', function (Blueprint $table) {
            $table->text('explanation_temp')->nullable()->after('phonetic_us');
        });

        // 2. 从 JSON 字段提取 zh 值到临时字段
        DB::statement("
            UPDATE words
            SET explanation_temp = JSON_UNQUOTE(JSON_EXTRACT(explanation, '$.zh'))
            WHERE explanation IS NOT NULL
            AND JSON_EXTRACT(explanation, '$.zh') IS NOT NULL
            AND JSON_UNQUOTE(JSON_EXTRACT(explanation, '$.zh')) != ''
        ");

        // 3. 删除旧的 JSON 字段
        Schema::table('words', function (Blueprint $table) {
            $table->dropColumn('explanation');
        });

        // 4. 重命名临时字段为 explanation(使用原生 SQL)
        DB::statement('ALTER TABLE words CHANGE explanation_temp explanation TEXT NULL COMMENT \'中文释义\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only run on MySQL - SQLite doesn't support JSON functions
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // 1. 添加临时 JSON 字段
        Schema::table('words', function (Blueprint $table) {
            $table->json('explanation_temp')->nullable()->after('phonetic_us');
        });

        // 2. 将字符串值转换为 JSON 格式 {zh: "...", en: ""}
        DB::statement("
            UPDATE words
            SET explanation_temp = JSON_OBJECT('zh', COALESCE(explanation, ''), 'en', '')
        ");

        // 3. 删除旧的 TEXT 字段
        Schema::table('words', function (Blueprint $table) {
            $table->dropColumn('explanation');
        });

        // 4. 重命名临时字段为 explanation(使用原生 SQL)
        DB::statement("ALTER TABLE words CHANGE explanation_temp explanation JSON NULL COMMENT '释义 JSON: {en: \"\", zh: \"\"}'");
    }
};
