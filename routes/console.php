<?php

use App\Jobs\SyncInstagramProfile;
use App\Models\CreatorProfile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    CreatorProfile::query()->where('instagram_connection_status', 'connected')->pluck('id')
        ->each(fn ($id) => SyncInstagramProfile::dispatch($id));
})->hourly();

/*
 | Shared hosting has no process manager, so the queue is drained from the
 | scheduler instead of a long-running worker. Keeping it here means the host
 | needs exactly one cron entry (schedule:run) rather than two, and
 | withoutOverlapping() stops a slow job from stacking up a second worker.
 |
 | max-time is 55s so the worker always exits before the next minute's tick.
 | On a VPS, run a real `queue:work` under Supervisor and delete this.
 */
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();
