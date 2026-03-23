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
        Schema::create('note_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name');
            $table->string('color')->default('#3b82f6'); // 默认蓝色
            $table->timestamps();
            $table->softDeletes();
        });

        // 创建笔记与标签的多对多关联表
        Schema::create('note_note_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('note_id')->index();
            $table->unsignedBigInteger('note_tag_id')->index();
            $table->timestamps();

            // 确保一个笔记不会重复添加同一个标签
            $table->unique(['note_id', 'note_tag_id']);
        });
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
