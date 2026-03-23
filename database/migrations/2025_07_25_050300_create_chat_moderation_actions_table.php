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
        Schema::create('chat_moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id')->index();
            $table->unsignedBigInteger('moderator_id')->index();
            $table->unsignedBigInteger('target_user_id')->nullable()->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->enum('action_type', ['delete_message', 'mute_user', 'unmute_user', 'timeout_user', 'ban_user', 'unban_user']);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // Store additional data like timeout duration
            $table->timestamps();

            $table->index(['room_id', 'created_at']);
            $table->index(['moderator_id', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_moderation_actions');
    }
};
