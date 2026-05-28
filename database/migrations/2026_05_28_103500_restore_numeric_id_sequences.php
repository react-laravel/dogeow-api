<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Restore PostgreSQL sequences for numeric primary-key id columns that were
     * imported without defaults. Without nextval() defaults, normal Eloquent
     * inserts omit id and fail with NOT NULL violations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
DO $$
DECLARE
    row record;
    sequence_name text;
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
            AND c.data_type = 'numeric'
            AND c.is_nullable = 'NO'
            AND tc.constraint_type = 'PRIMARY KEY'
            AND c.column_default IS NULL
        ORDER BY c.table_name
    LOOP
        sequence_name := row.table_name || '_id_seq';

        EXECUTE format('CREATE SEQUENCE IF NOT EXISTS %I OWNED BY %I.id', sequence_name, row.table_name);
        EXECUTE format('ALTER TABLE %I ALTER COLUMN id SET DEFAULT nextval(%L::regclass)', row.table_name, sequence_name);
        EXECUTE format(
            'SELECT setval(%L, COALESCE((SELECT MAX(id)::bigint FROM %I), 0) + 1, false)',
            sequence_name,
            row.table_name
        );
    END LOOP;
END $$;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
DO $$
DECLARE
    row record;
    sequence_name text;
BEGIN
    FOR row IN
        SELECT c.table_name
        FROM information_schema.columns c
        WHERE c.table_schema = current_schema()
            AND c.column_name = 'id'
            AND c.data_type = 'numeric'
            AND c.column_default LIKE 'nextval(%_id_seq%'
            AND c.table_name <> 'thing_items'
        ORDER BY c.table_name
    LOOP
        sequence_name := row.table_name || '_id_seq';
        EXECUTE format('ALTER TABLE %I ALTER COLUMN id DROP DEFAULT', row.table_name);
        EXECUTE format('DROP SEQUENCE IF EXISTS %I', sequence_name);
    END LOOP;
END $$;
SQL);
    }
};
