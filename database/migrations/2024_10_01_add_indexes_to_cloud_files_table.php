<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 添加复合索引优化常用查询
        Schema::table('cloud_files', function (Blueprint $table) {
            // user_id + parent_id 组合索引 - 用于获取用户指定文件夹下的文件
            $table->index(['user_id', 'parent_id'], 'cloud_files_user_parent_idx');

            // user_id + is_folder 组合索引 - 用于获取用户的文件夹列表
            $table->index(['user_id', 'is_folder'], 'cloud_files_user_folder_idx');

            // user_id + parent_id + is_folder 组合索引 - 用于树形结构查询
            $table->index(['user_id', 'parent_id', 'is_folder'], 'cloud_files_user_parent_folder_idx');

            // extension 索引 - 用于文件类型过滤
            $table->index('extension', 'cloud_files_extension_idx');

            // created_at 索引 - 用于排序
            $table->index('created_at', 'cloud_files_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cloud_files', function (Blueprint $table) {
            $table->dropIndex('cloud_files_user_parent_idx');
            $table->dropIndex('cloud_files_user_folder_idx');
            $table->dropIndex('cloud_files_user_parent_folder_idx');
            $table->dropIndex('cloud_files_extension_idx');
            $table->dropIndex('cloud_files_created_at_idx');
        });
    }
};
