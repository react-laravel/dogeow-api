<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_lists', function (Blueprint $table) {
            $table->id()->comment('列表 ID');
            $table->unsignedBigInteger('user_id')->index()->comment('所属用户 ID');
            $table->string('name')->comment('列表名称');
            $table->string('description')->nullable()->comment('列表描述');
            $table->unsignedInteger('position')->default(0)->comment('列表排序位置');
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'todo_lists_user_name_unique');
            $table->index(['user_id', 'position'], 'todo_lists_user_position_idx');
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE todo_lists COMMENT = '待办列表表'");
        }

        Schema::create('todo_tasks', function (Blueprint $table) {
            $table->id()->comment('任务 ID');
            $table->unsignedBigInteger('todo_list_id')->index()->comment('所属列表 ID');
            $table->string('title')->comment('任务标题');
            $table->boolean('is_completed')->default(false)->comment('是否已完成');
            $table->unsignedInteger('position')->default(0)->comment('任务排序位置');
            $table->timestamps();

            $table->index(['todo_list_id', 'is_completed', 'position'], 'todo_tasks_list_state_position_idx');
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE todo_tasks COMMENT = '待办任务表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_tasks');
        Schema::dropIfExists('todo_lists');
    }
};
