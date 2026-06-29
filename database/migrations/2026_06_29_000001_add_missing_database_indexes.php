<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $result = DB::selectOne(
                'SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?',
                [$indexName]
            );

            return $result !== null;
        }

        if ($driver === 'sqlite') {
            $result = DB::selectOne(
                'SELECT name FROM sqlite_master WHERE type = ? AND tbl_name = ? AND name = ?',
                ['index', $table, $indexName]
            );

            return $result !== null;
        }

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            );

            return $result !== null;
        }

        return false;
    }

    public function up(): void
    {
        // game_characters — user_id, is_fighting, difficulty_tier
        Schema::table('game_characters', function (Blueprint $table) {
            if (! $this->indexExists('game_characters', 'game_characters_user_id_idx')) {
                $table->index('user_id', 'game_characters_user_id_idx');
            }
            if (! $this->indexExists('game_characters', 'game_characters_is_fighting_idx')) {
                $table->index('is_fighting', 'game_characters_is_fighting_idx');
            }
            if (! $this->indexExists('game_characters', 'game_characters_difficulty_fighting_idx')) {
                $table->index(['difficulty_tier', 'is_fighting'], 'game_characters_difficulty_fighting_idx');
            }
        });

        // chat_messages — room_id+created_at cursor, user_id+created_at
        Schema::table('chat_messages', function (Blueprint $table) {
            if (! $this->indexExists('chat_messages', 'chat_messages_room_time_idx')) {
                $table->index(['room_id', 'created_at'], 'chat_messages_room_time_idx');
            }
            if (! $this->indexExists('chat_messages', 'chat_messages_user_time_idx')) {
                $table->index(['user_id', 'created_at'], 'chat_messages_user_time_idx');
            }
        });

        // game_items — character_id + is_equipped composite
        Schema::table('game_items', function (Blueprint $table) {
            if (! $this->indexExists('game_items', 'game_items_character_equipped_idx')) {
                $table->index(['character_id', 'is_equipped'], 'game_items_character_equipped_idx');
            }
        });

        // chat_room_users — room_id+is_online, user_id+is_online
        Schema::table('chat_room_users', function (Blueprint $table) {
            if (! $this->indexExists('chat_room_users', 'chat_room_users_room_online_idx')) {
                $table->index(['room_id', 'is_online'], 'chat_room_users_room_online_idx');
            }
            if (! $this->indexExists('chat_room_users', 'chat_room_users_user_online_idx')) {
                $table->index(['user_id', 'is_online'], 'chat_room_users_user_online_idx');
            }
        });

        // notes — user_id+is_wiki, is_wiki+slug
        Schema::table('notes', function (Blueprint $table) {
            if (! $this->indexExists('notes', 'notes_user_wiki_idx')) {
                $table->index(['user_id', 'is_wiki'], 'notes_user_wiki_idx');
            }
            if (! $this->indexExists('notes', 'notes_wiki_slug_idx')) {
                $table->index(['is_wiki', 'slug'], 'notes_wiki_slug_idx');
            }
        });

        // game_combat_logs — character_id+created_at
        Schema::table('game_combat_logs', function (Blueprint $table) {
            if (! $this->indexExists('game_combat_logs', 'game_combat_logs_character_time_idx')) {
                $table->index(['character_id', 'created_at'], 'game_combat_logs_character_time_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_characters', function (Blueprint $table) {
            $table->dropIndex('game_characters_user_id_idx');
            $table->dropIndex('game_characters_is_fighting_idx');
            $table->dropIndex('game_characters_difficulty_fighting_idx');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('chat_messages_room_time_idx');
            $table->dropIndex('chat_messages_user_time_idx');
        });

        Schema::table('game_items', function (Blueprint $table) {
            $table->dropIndex('game_items_character_equipped_idx');
        });

        Schema::table('chat_room_users', function (Blueprint $table) {
            $table->dropIndex('chat_room_users_room_online_idx');
            $table->dropIndex('chat_room_users_user_online_idx');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_user_wiki_idx');
            $table->dropIndex('notes_wiki_slug_idx');
        });

        Schema::table('game_combat_logs', function (Blueprint $table) {
            $table->dropIndex('game_combat_logs_character_time_idx');
        });
    }
};
