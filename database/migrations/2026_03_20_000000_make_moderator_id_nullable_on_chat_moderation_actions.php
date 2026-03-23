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
        Schema::table('chat_moderation_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('moderator_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_moderation_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('moderator_id')->change();
        });
    }
};
