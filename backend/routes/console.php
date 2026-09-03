<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Automate the time-based business jobs. Each command respects its module's
| feature flag and is idempotent, so a missed or repeated run is safe. A single
| `php artisan schedule:run` every minute (cron) — or the `scheduler` service in
| docker-compose running `schedule:work` — drives them all.
*/

// Business-state alerts (cheques/traites due, overdue instalments, low stock…).
Schedule::command('notifications:scan')->dailyAt('06:00')->withoutOverlapping();

// Escalating reminders on overdue invoices.
Schedule::command('dunning:run')->dailyAt('07:00')->withoutOverlapping();

// Invoices for subscriptions whose period has come due.
Schedule::command('subscriptions:bill')->dailyAt('02:00')->withoutOverlapping();

// Monthly straight-line depreciation of active fixed assets.
Schedule::command('assets:depreciate')->monthlyOn(1, '03:00')->withoutOverlapping();
