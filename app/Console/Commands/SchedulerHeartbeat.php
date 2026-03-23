<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'scheduler:heartbeat';

    protected $description = '更新调度器心跳时间戳';

    public function handle(): int
    {
        Cache::put('scheduler:heartbeat', Carbon::now()->toIso8601String(), 600);

        $this->info('Scheduler heartbeat updated at ' . now()->toDateTimeString());

        return self::SUCCESS;
    }
}
