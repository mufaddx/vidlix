<?php

use App\Jobs\SyncInstagramProfile;
use App\Models\CreatorProfile;
use App\Services\Deals\NegotiationService;
use App\Services\Payments\Reconciliation;
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

/*
 | Reminders go out once a day rather than hourly: a nudge that arrives every
 | hour is noise, and noise gets muted. The command guards against sending the
 | same reminder twice, so a scheduler that fires more than once - which the
 | HTTP-triggered one can - does not turn one nudge into several.
 */
Schedule::command('vidlix:reminders')->dailyAt('09:00');

/*
 | Payments nobody heard back about.
 |
 | Webhooks get lost — provider outages, our own queue down, a delivery that
 | simply never arrives — and a lost one leaves a payment in limbo while the
 | money has already moved. Waiting for a customer to complain is not a
 | reconciliation strategy.
 |
 | withoutOverlapping because a slow provider must not let two sweeps ask about
 | the same payments at once.
 */
Schedule::call(function () {
    app(Reconciliation::class)->run();
    // A closure needs a name before it can be locked against overlap; without
    // one there is nothing for the mutex to key on.
})->name('payments-reconciliation')->hourly()->withoutOverlapping();

/*
 | Offers nobody answered. Expiry is a status change rather than a deletion,
 | because "they never replied" is itself worth being able to see.
 */
Schedule::call(function () {
    app(NegotiationService::class)->expireOverdue();
})->dailyAt('02:00');
