<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建房间表迁移
 * 包含房间名称、所属区域和所属用户
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建 rooms 表，包含以下字段：
     * - id: 主键
     * - name: 房间名称
     * - area_id: 所属区域 ID
     * - user_id: 所属用户 ID
     */
    public function up(): void
    {
        Schema::create('thing_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     * 删除 rooms 表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_rooms');
    }
};
