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
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('word_book_id')->comment('单词书 ID');
            $table->string('content')->comment('单词内容');
            $table->string('phonetic_uk')->nullable()->comment('英式音标');
            $table->string('phonetic_us')->nullable()->comment('美式音标');
            $table->json('explanation')->nullable()->comment('释义 JSON: {en: "", zh: ""}');
            $table->json('example_sentences')->nullable()->comment('例句 JSON: [{en: "", zh: ""}]');
            $table->integer('difficulty')->default(1)->comment('难度等级 1-5');
            $table->integer('frequency')->default(1)->comment('词频等级 1-5');
            $table->timestamps();

            $table->index('word_book_id');
            $table->index('content');
            $table->index('difficulty');
            $table->index('frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
