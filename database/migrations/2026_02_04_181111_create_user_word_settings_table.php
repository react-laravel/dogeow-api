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
        Schema::create('user_word_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户 ID');
            $table->integer('daily_new_words')->default(10)->comment('每日新学单词数');
            $table->integer('review_multiplier')->default(2)->comment('复习倍数 1/2/3');
            $table->unsignedBigInteger('current_book_id')->nullable()->comment('当前学习的单词书 ID');
            $table->boolean('is_auto_pronounce')->default(true)->comment('是否自动发音');
            $table->timestamps();

            $table->unique('user_id');
            $table->index('current_book_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_word_settings');
    }
};
