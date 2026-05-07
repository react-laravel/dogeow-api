<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function table(string $table, callable $callback): void
    {
        if (Schema::hasTable($table)) {
            Schema::table($table, $callback);
        }
    }

    public function up(): void
    {
        $this->table('note_categories', function (Blueprint $table): void {
            $table->unique(['user_id', 'name'], 'note_categories_user_name_unique');
        });

        $this->table('note_tags', function (Blueprint $table): void {
            $table->unique(['user_id', 'name'], 'note_tags_user_name_unique');
        });

        $this->table('thing_items', function (Blueprint $table): void {
            $table->index(['user_id', 'status'], 'thing_items_user_status_idx');
            $table->index(['user_id', 'category_id'], 'thing_items_user_category_idx');
            $table->index(['user_id', 'area_id', 'room_id', 'spot_id'], 'thing_items_user_location_idx');
            $table->index(['user_id', 'expiry_date'], 'thing_items_user_expiry_idx');
        });

        $this->table('todo_lists', function (Blueprint $table): void {
            $table->unique(['user_id', 'name'], 'todo_lists_user_name_unique');
            $table->index(['user_id', 'position'], 'todo_lists_user_position_idx');
        });

        $this->table('todo_tasks', function (Blueprint $table): void {
            $table->index(['todo_list_id', 'is_completed', 'position'], 'todo_tasks_list_state_position_idx');
        });

        $this->table('user_words', function (Blueprint $table): void {
            $table->unique(['user_id', 'word_id', 'word_book_id'], 'user_words_unique_learning_item');
            $table->index(['user_id', 'word_book_id', 'status'], 'user_words_user_book_status_idx');
            $table->index(['user_id', 'next_review_at'], 'user_words_user_next_review_idx');
        });

        $this->table('user_word_check_ins', function (Blueprint $table): void {
            $table->index(['user_id', 'check_in_date'], 'user_word_checkins_user_date_idx');
        });

        $this->table('game_items', function (Blueprint $table): void {
            $table->unique(
                ['character_id', 'is_in_storage', 'slot_index'],
                'game_items_character_storage_slot_unique'
            );
            $table->index(['character_id', 'is_equipped'], 'game_items_character_equipped_idx');
            $table->index(['character_id', 'definition_id'], 'game_items_character_definition_idx');
        });

        $this->table('game_equipment', function (Blueprint $table): void {
            $table->unique('item_id', 'game_equipment_item_unique');
        });

        $this->table('game_item_gems', function (Blueprint $table): void {
            $table->unique(['item_id', 'socket_index'], 'game_item_gems_item_socket_unique');
        });

        $this->table('game_character_skills', function (Blueprint $table): void {
            $table->unique(['character_id', 'slot_index'], 'game_character_skills_slot_unique');
        });

        $this->table('game_combat_logs', function (Blueprint $table): void {
            $table->index(['character_id', 'victory', 'created_at'], 'game_combat_logs_character_result_idx');
        });

        $this->table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_idx');
        });
    }

    public function down(): void
    {
        $this->table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_read_idx');
        });

        $this->table('game_combat_logs', function (Blueprint $table): void {
            $table->dropIndex('game_combat_logs_character_result_idx');
        });

        $this->table('game_character_skills', function (Blueprint $table): void {
            $table->dropUnique('game_character_skills_slot_unique');
        });

        $this->table('game_item_gems', function (Blueprint $table): void {
            $table->dropUnique('game_item_gems_item_socket_unique');
        });

        $this->table('game_equipment', function (Blueprint $table): void {
            $table->dropUnique('game_equipment_item_unique');
        });

        $this->table('game_items', function (Blueprint $table): void {
            $table->dropIndex('game_items_character_definition_idx');
            $table->dropIndex('game_items_character_equipped_idx');
            $table->dropUnique('game_items_character_storage_slot_unique');
        });

        $this->table('user_word_check_ins', function (Blueprint $table): void {
            $table->dropIndex('user_word_checkins_user_date_idx');
        });

        $this->table('user_words', function (Blueprint $table): void {
            $table->dropIndex('user_words_user_next_review_idx');
            $table->dropIndex('user_words_user_book_status_idx');
            $table->dropUnique('user_words_unique_learning_item');
        });

        $this->table('todo_tasks', function (Blueprint $table): void {
            $table->dropIndex('todo_tasks_list_state_position_idx');
        });

        $this->table('todo_lists', function (Blueprint $table): void {
            $table->dropIndex('todo_lists_user_position_idx');
            $table->dropUnique('todo_lists_user_name_unique');
        });

        $this->table('thing_items', function (Blueprint $table): void {
            $table->dropIndex('thing_items_user_expiry_idx');
            $table->dropIndex('thing_items_user_location_idx');
            $table->dropIndex('thing_items_user_category_idx');
            $table->dropIndex('thing_items_user_status_idx');
        });

        $this->table('note_tags', function (Blueprint $table): void {
            $table->dropUnique('note_tags_user_name_unique');
        });

        $this->table('note_categories', function (Blueprint $table): void {
            $table->dropUnique('note_categories_user_name_unique');
        });
    }
};
