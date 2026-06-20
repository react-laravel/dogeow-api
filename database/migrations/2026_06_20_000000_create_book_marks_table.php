<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_marks', function (Blueprint $table) {
            $table->string('id')->primary()->comment('书签 ID');
            $table->unsignedBigInteger('user_id')->index()->comment('所属用户 ID');
            $table->string('book_id', 64)->index()->comment('书籍 ID');
            $table->string('kind', 32)->index()->comment('类型：position 或 collection');
            $table->unsignedInteger('chapter_id')->comment('章节 ID');
            $table->string('chapter_title')->comment('章节标题');
            $table->decimal('scroll_top', 12, 2)->default(0)->comment('滚动位置');
            $table->integer('pair_index')->nullable()->comment('段落索引');
            $table->string('position_key')->nullable()->comment('同位置去重键');
            $table->text('excerpt')->nullable()->comment('摘录');
            $table->text('note')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_at_ms')->comment('前端创建时间戳');
            $table->timestamps();

            $table->index(['user_id', 'book_id', 'created_at_ms']);
            $table->unique(['user_id', 'book_id', 'kind', 'position_key'], 'book_marks_unique_position');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE book_marks COMMENT = '用户书籍书签表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('book_marks');
    }
};
