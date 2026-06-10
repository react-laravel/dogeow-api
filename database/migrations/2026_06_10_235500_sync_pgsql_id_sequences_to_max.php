<?php

use App\Support\Database\PostgresSequenceSynchronizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Re-sync PostgreSQL id sequences when they lag behind MAX(id), which causes
     * duplicate primary-key errors (e.g. game_character_skills_pkey).
     */
    public function up(): void
    {
        PostgresSequenceSynchronizer::syncAll();
    }

    public function down(): void
    {
        // Prior sequence positions cannot be restored reliably.
    }
};
