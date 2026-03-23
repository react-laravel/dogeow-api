<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建物品关联表迁移
 * 用于建立物品之间的关联关系(例如：配件、替换品、相关物品等)
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建 item_relations 表，包含以下字段：
     * - id: 主键
     * - item_id: 物品 ID
     * - related_item_id: 关联物品 ID
     * - relation_type: 关联类型(accessory 配件、replacement 替换品、related 相关、bundle 套装、parent 父物品、child 子物品)
     * - description: 关联描述
     * - created_at, updated_at: 时间戳
     */
    public function up(): void
    {
        Schema::create('thing_item_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->comment('物品 ID');
            $table->unsignedBigInteger('related_item_id')->comment('关联物品 ID');
            $table->enum('relation_type', ['accessory', 'replacement', 'related', 'bundle', 'parent', 'child'])
                ->default('related')
                ->comment('关联类型：配件、替换品、相关、套装、父物品、子物品');
            $table->text('description')->nullable()->comment('关联描述');
            $table->timestamps();

            // 索引
            $table->index('item_id');
            $table->index('related_item_id');
            $table->index('relation_type');

            // 唯一约束：同一对物品的同一类型关联只能存在一次
            $table->unique(['item_id', 'related_item_id', 'relation_type'], 'tir_item_related_type_unique');
        });
    }

    /**
     * 回滚迁移
     * 删除 item_relations 表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_item_relations');
    }
};
