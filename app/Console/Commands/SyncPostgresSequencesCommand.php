<?php

namespace App\Console\Commands;

use App\Support\Database\PostgresSequenceSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncPostgresSequencesCommand extends Command
{
    protected $signature = 'db:sync-sequences';

    protected $description = 'Re-sync PostgreSQL id sequences to MAX(id) for all tables';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('This command only applies to PostgreSQL connections.');

            return self::SUCCESS;
        }

        PostgresSequenceSynchronizer::syncAll();
        $this->info('PostgreSQL id sequences synced to MAX(id).');

        return self::SUCCESS;
    }
}
