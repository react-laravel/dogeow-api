<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 单词书与教育级别的多对多关联表
     */
    public function up(): void
    {
        Schema::create('word_book_education_level', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('word_book_id')->comment('单词书 ID');
            $table->unsignedBigInteger('education_level_id')->comment('教育级别 ID');
            $table->timestamps();

            $table->unique(['word_book_id', 'education_level_id']);
            $table->index('word_book_id');
            $table->index('education_level_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_book_education_level');
    }
};
