<?php

use App\Jobs\ProcessSavedSearchNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| The following tasks run on a schedule. Ensure `php artisan schedule:run`
| is executed every minute via cron:
|
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Nightly maintenance tasks (2:00 AM server time)
Schedule::command('listing-media:prune --force')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduled-tasks.log'))
    ->description('Prune orphaned media files from storage');

Schedule::command('listings:cleanup-deleted --force')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduled-tasks.log'))
    ->description('Clean up media for listings soft-deleted beyond retention period');

// Weekly hard-delete of very old soft-deleted listings (Sundays at 3:00 AM)
Schedule::command('listings:cleanup-deleted --days=90 --hard-delete --force')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduled-tasks.log'))
    ->description('Hard-delete listings soft-deleted more than 90 days ago');

/*
|--------------------------------------------------------------------------
| Saved Search Notifications
|--------------------------------------------------------------------------
*/

// Instant notifications - run every 5 minutes
Schedule::job(new ProcessSavedSearchNotifications('instant'))
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Process instant saved search notifications');

// Daily digest - run at 8:00 AM
Schedule::job(new ProcessSavedSearchNotifications('daily'))
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->description('Process daily saved search digest notifications');

// Weekly digest - run on Mondays at 8:00 AM
Schedule::job(new ProcessSavedSearchNotifications('weekly'))
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping()
    ->description('Process weekly saved search digest notifications');
