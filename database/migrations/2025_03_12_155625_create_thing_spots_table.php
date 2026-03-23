<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建具体位置表迁移
 * 包含位置名称、所属房间和所属用户
 */
return new class extends Migration
{
    /**
     * 运行迁移
     * 创建 spots 表，包含以下字段：
     * - id: 主键
     * - name: 位置名称
     * - room_id: 所属房间 ID
     * - user_id: 所属用户 ID
     */
    public function up(): void
    {
        Schema::create('thing_spots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     * 删除 spots 表
     */
    public function down(): void
    {
        Schema::dropIfExists('thing_spots');
    }
};
