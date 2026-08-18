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
