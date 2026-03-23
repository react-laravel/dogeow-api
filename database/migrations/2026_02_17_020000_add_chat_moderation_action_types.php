<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if running on MySQL
     */
    private function isMySQL(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    /**
     * Run the migrations.
     * Add content_filter, spam_detection, report_message to action_type enum.
     */
    public function up(): void
    {
        if (! $this->isMySQL()) {
            return;
        }

        DB::statement("ALTER TABLE chat_moderation_actions MODIFY COLUMN action_type ENUM(
            'delete_message',
            'mute_user',
            'unmute_user',
            'timeout_user',
            'ban_user',
            'unban_user',
            'content_filter',
            'spam_detection',
            'report_message'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->isMySQL()) {
            return;
        }

        DB::statement("ALTER TABLE chat_moderation_actions MODIFY COLUMN action_type ENUM(
            'delete_message',
            'mute_user',
            'unmute_user',
            'timeout_user',
            'ban_user',
            'unban_user'
        ) NOT NULL");
    }
};
