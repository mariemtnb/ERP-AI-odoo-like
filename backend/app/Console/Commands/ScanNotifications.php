<?php

namespace App\Console\Commands;

use App\Services\NotificationScanner;
use Illuminate\Console\Command;

/**
 * `php artisan notifications:scan`
 *
 * Generates the time-based notifications (cheques due, instalments overdue,
 * low stock, pending approvals). Meant to run on a schedule — but note the
 * project's deployment has no queue/scheduler worker yet, so for now it is run
 * by hand or through the POST /notifications/scan endpoint.
 */
class ScanNotifications extends Command
{
    protected $signature = 'notifications:scan';

    protected $description = 'Generate notifications from the current state of the business';

    public function handle(): int
    {
        $result = NotificationScanner::scan();

        foreach ($result as $detector => $count) {
            $this->line(sprintf('  %-24s %d', $detector, $count));
        }
        $this->info('Created ' . array_sum($result) . ' notification(s).');

        return self::SUCCESS;
    }
}
