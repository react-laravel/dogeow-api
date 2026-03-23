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
        Schema::create('word_books', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('word_category_id')->comment('单词分类 ID');
            $table->string('name')->comment('单词书名称');
            $table->text('description')->nullable()->comment('单词书描述');
            $table->integer('difficulty')->default(1)->comment('难度等级 1-5');
            $table->integer('total_words')->default(0)->comment('总单词数');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->index('word_category_id');
            $table->index('difficulty');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_books');
    }
};
