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
        if (! Schema::hasTable('user_words')) {
            return;
        }

        Schema::table('user_words', function (Blueprint $table) {
            if (! Schema::hasColumn('user_words', 'stage')) {
                $table->integer('stage')->default(0)->comment('复习阶段 0-7 (艾宾浩斯)')->after('status');
            }
            if (! Schema::hasColumn('user_words', 'ease_factor')) {
                $table->decimal('ease_factor', 3, 2)->default(2.50)->comment('难度因子 (SM-2 算法)')->after('stage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('user_words')) {
            return;
        }

        Schema::table('user_words', function (Blueprint $table) {
            if (Schema::hasColumn('user_words', 'stage')) {
                $table->dropColumn('stage');
            }
            if (Schema::hasColumn('user_words', 'ease_factor')) {
                $table->dropColumn('ease_factor');
            }
        });
    }
};
