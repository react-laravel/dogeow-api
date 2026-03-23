<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 调度器心跳，用于监控调度器是否正常运行
Schedule::command('scheduler:heartbeat')
    ->everyMinute()
    ->name('scheduler-heartbeat')
    ->withoutOverlapping();

Schedule::command('repo-watch:refresh')
    ->everyThirtyMinutes()
    ->name('repo-watch-refresh')
    ->withoutOverlapping();
