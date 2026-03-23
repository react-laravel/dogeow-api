<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建物品图片表迁移
 * 包含图片路径、缩略图路径、是否为主图、排序等信息
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建 item_images 表，包含以下字段：
     * - id: 主键
     * - item_id: 物品 ID
     * - path: 图片路径
     * - is_primary: 是否为主图
     * - sort_order: 排序顺序
     */
    public function up(): void
    {
        Schema::create('thing_item_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     * 删除 item_images 表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_item_images');
    }
};
