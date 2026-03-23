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
        Schema::table('game_items', function (Blueprint $table) {
            $table->boolean('is_equipped')->default(false)->after('is_in_storage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_items', function (Blueprint $table) {
            $table->dropColumn('is_equipped');
        });
    }
};
