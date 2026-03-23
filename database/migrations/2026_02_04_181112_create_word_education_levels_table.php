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
        // 创建教育级别表
        Schema::create('word_education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('级别代码：junior_high, senior_high, cet4, cet6, postgraduate');
            $table->string('name')->comment('级别名称：初中、高中、CET4、CET6、考研');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->index('sort_order');
        });

        // 创建单词和教育级别的关联表
        Schema::create('word_education_level', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('word_id')->comment('单词 ID');
            $table->unsignedBigInteger('education_level_id')->comment('教育级别 ID');
            $table->timestamps();

            $table->unique(['word_id', 'education_level_id']);
            $table->index('word_id');
            $table->index('education_level_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_education_level');
        Schema::dropIfExists('word_education_levels');
    }
};
