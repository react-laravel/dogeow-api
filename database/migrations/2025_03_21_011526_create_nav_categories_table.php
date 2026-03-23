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
        Schema::create('nav_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('分类名称');
            $table->string('icon', 100)->nullable()->comment('分类图标');
            $table->text('description')->nullable()->comment('分类描述');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->boolean('is_visible')->default(true)->comment('是否可见');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nav_categories');
    }
};
