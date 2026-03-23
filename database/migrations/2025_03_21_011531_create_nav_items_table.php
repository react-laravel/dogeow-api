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
        Schema::create('nav_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nav_category_id')->comment('导航分类 ID')->index();
            $table->string('name', 50)->comment('导航名称');
            $table->string('url', 255)->comment('链接地址');
            $table->string('icon', 100)->nullable()->comment('图标');
            $table->text('description')->nullable()->comment('描述');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->boolean('is_visible')->default(true)->comment('是否可见');
            $table->boolean('is_new_window')->default(false)->comment('是否新窗口打开');
            $table->integer('clicks')->default(0)->comment('点击次数');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nav_items');
    }
};
