<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建物品分类表迁移
 * 包含分类名称、父分类 ID 和所属用户，支持两级分类
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建 item_categories 表，包含以下字段：
     * - id: 主键
     * - name: 分类名称
     * - parent_id: 父分类 ID(可为空，支持两级分类)
     * - user_id: 所属用户 ID
     */
    public function up(): void
    {
        Schema::create('thing_item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    /**
     * 回滚迁移
     * 删除 item_categories 表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_item_categories');
    }
};
