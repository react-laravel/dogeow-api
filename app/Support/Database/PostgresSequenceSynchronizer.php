<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

class PostgresSequenceSynchronizer
{
    /**
     * Align PostgreSQL serial sequences with MAX(id) for all tables in the current schema.
     *
     * Manual imports, seeders, or tests that insert explicit ids can leave sequences behind
     * and cause duplicate primary-key errors on normal Eloquent inserts.
     */
    public static function syncAll(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
DO $$
DECLARE
    row record;
    sequence_name text;
    max_id bigint;
BEGIN
    FOR row IN
        SELECT c.table_name
        FROM information_schema.columns c
        JOIN information_schema.key_column_usage kcu
            ON kcu.table_schema = c.table_schema
            AND kcu.table_name = c.table_name
            AND kcu.column_name = c.column_name
        JOIN information_schema.table_constraints tc
            ON tc.constraint_schema = kcu.constraint_schema
            AND tc.constraint_name = kcu.constraint_name
            AND tc.table_name = kcu.table_name
        WHERE c.table_schema = current_schema()
            AND c.column_name = 'id'
            AND tc.constraint_type = 'PRIMARY KEY'
        ORDER BY c.table_name
    LOOP
        SELECT pg_get_serial_sequence(row.table_name, 'id') INTO sequence_name;

        IF sequence_name IS NOT NULL THEN
            EXECUTE format('SELECT COALESCE(MAX(id)::bigint, 0) FROM %I', row.table_name) INTO max_id;
            EXECUTE format('SELECT setval(%L, %s + 1, false)', sequence_name, max_id);
        END IF;
    END LOOP;
END $$;
SQL);
    }
}
