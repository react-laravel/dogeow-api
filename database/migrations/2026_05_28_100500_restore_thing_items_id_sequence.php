<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Restore PostgreSQL auto-increment behavior for thing_items.id.
     *
     * The production schema was missing a default sequence on thing_items.id, so
     * inserts that rely on Laravel/Eloquent's normal auto-increment key omitted
     * id and failed with: null value in column "id" violates not-null constraint.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
DO $$
DECLARE
    sequence_name text;
BEGIN
    SELECT pg_get_serial_sequence('thing_items', 'id') INTO sequence_name;

    IF sequence_name IS NULL THEN
        CREATE SEQUENCE IF NOT EXISTS thing_items_id_seq OWNED BY thing_items.id;
        ALTER TABLE thing_items ALTER COLUMN id SET DEFAULT nextval('thing_items_id_seq');
        sequence_name := 'thing_items_id_seq';
    END IF;

    EXECUTE format(
        'SELECT setval(%L, COALESCE((SELECT MAX(id)::bigint FROM thing_items), 0) + 1, false)',
        sequence_name
    );
END $$;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE thing_items ALTER COLUMN id DROP DEFAULT");
        DB::statement("DROP SEQUENCE IF EXISTS thing_items_id_seq");
    }
};
