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
        if (Schema::hasColumn('chat_rooms', 'is_private')) {
            return;
        }

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('is_active');
            $table->index(['is_active', 'is_private']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'is_private']);
            $table->dropColumn('is_private');
        });
    }
};
