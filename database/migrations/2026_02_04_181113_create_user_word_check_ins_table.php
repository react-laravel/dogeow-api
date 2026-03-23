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
        Schema::create('user_word_check_ins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户 ID');
            $table->date('check_in_date')->comment('打卡日期');
            $table->integer('new_words_count')->default(0)->comment('新学单词数');
            $table->integer('review_words_count')->default(0)->comment('复习单词数');
            $table->integer('study_duration')->default(0)->comment('学习时长(秒)');
            $table->timestamps();

            $table->unique(['user_id', 'check_in_date']);
            $table->index('check_in_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_word_check_ins');
    }
};
