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
        Schema::create('note_tags', function (Blueprint $table) {
            $table->id()->comment('标签 ID');
            $table->unsignedBigInteger('user_id')->index()->comment('所属用户 ID');
            $table->string('name')->comment('标签名称');
            $table->string('color')->default('#3b82f6')->comment('标签颜色（默认蓝色）');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'name'], 'note_tags_user_name_unique');
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE note_tags COMMENT = '笔记标签表'");
        }

        // 创建笔记与标签的多对多关联表
        Schema::create('note_note_tag', function (Blueprint $table) {
            $table->id()->comment('记录 ID');
            $table->unsignedBigInteger('note_id')->index()->comment('笔记 ID');
            $table->unsignedBigInteger('note_tag_id')->index()->comment('标签 ID');
            $table->timestamps();

            $table->unique(['note_id', 'note_tag_id']);
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE note_note_tag COMMENT = '笔记-标签关联表（多对多）'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_note_tag');
        Schema::dropIfExists('note_tags');
    }
};
