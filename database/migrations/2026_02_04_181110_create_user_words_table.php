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
        Schema::create('user_words', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户 ID');
            $table->unsignedBigInteger('word_id')->comment('单词 ID');
            $table->unsignedBigInteger('word_book_id')->comment('单词书 ID');
            $table->integer('status')->default(0)->comment('学习状态: 0=未学习, 1=学习中, 2=已掌握, 3=困难词');
            $table->integer('stage')->default(0)->comment('复习阶段 0-7 (艾宾浩斯)');
            $table->decimal('ease_factor', 3, 2)->default(2.50)->comment('难度因子 (SM-2 算法)');
            $table->integer('review_count')->default(0)->comment('复习次数');
            $table->integer('correct_count')->default(0)->comment('正确次数');
            $table->integer('wrong_count')->default(0)->comment('错误次数');
            $table->boolean('is_favorite')->default(false)->comment('是否收藏(生词本)');
            $table->timestamp('last_review_at')->nullable()->comment('最后复习时间');
            $table->timestamp('next_review_at')->nullable()->comment('下次复习时间');
            $table->text('personal_note')->nullable()->comment('个人笔记');
            $table->timestamps();

            // 索引
            $table->index(['user_id', 'word_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_favorite']);
            $table->index('next_review_at');
            $table->unique(['user_id', 'word_id', 'word_book_id'], 'user_words_unique_learning_item');
            $table->index(['user_id', 'word_book_id', 'status'], 'user_words_user_book_status_idx');
            $table->index(['user_id', 'next_review_at'], 'user_words_user_next_review_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_words');
    }
};
