<?php

namespace Tests\Unit\Support;

use App\Support\Database\PostgresSequenceSynchronizer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresSequenceSynchronizerTest extends TestCase
{
    public function test_sync_all_is_no_op_on_non_pgsql_connections(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->markTestSkipped('This guard test only runs on non-PostgreSQL connections.');
        }

        PostgresSequenceSynchronizer::syncAll();

        $this->assertNotSame('pgsql', DB::getDriverName());
    }
}
