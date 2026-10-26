<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Ingestion only refreshes the review queue. Nothing becomes public until a
 * staff reviewer approves it, so a tight refresh cadence cannot publish on its
 * own.
 */
Schedule::command('opportunities:ingest')
    ->everyThirtyMinutes()
    ->withoutOverlapping(20)
    ->runInBackground();

Schedule::command('opportunities:expire')
    ->hourly()
    ->withoutOverlapping(10);
