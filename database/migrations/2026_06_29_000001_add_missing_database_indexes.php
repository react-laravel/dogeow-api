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
        // notes — user_id+is_wiki, is_wiki+slug
        Schema::table('notes', function (Blueprint $table) {
            if (! $this->indexExists('notes', 'notes_user_wiki_idx')) {
                $table->index(['user_id', 'is_wiki'], 'notes_user_wiki_idx');
            }
            if (! $this->indexExists('notes', 'notes_wiki_slug_idx')) {
                $table->index(['is_wiki', 'slug'], 'notes_wiki_slug_idx');
            }
        });

    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_user_wiki_idx');
            $table->dropIndex('notes_wiki_slug_idx');
        });

    }
};
