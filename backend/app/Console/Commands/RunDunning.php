<?php

namespace App\Console\Commands;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\DunningService;
use Illuminate\Console\Command;

/**
 * `php artisan dunning:run`
 *
 * Sends every due overdue-invoice reminder (see DunningService). Meant to run
 * daily on the scheduler; the reminders are idempotent, so a repeat run the
 * same day sends nothing new.
 */
class RunDunning extends Command
{
    protected $signature = 'dunning:run {--as-of= : Reporting date (default today)}';

    protected $description = 'Send due AR dunning reminders';

    public function handle(): int
    {
        if (! FeatureFlag::enabled('dunning')) {
            $this->warn('Dunning module is disabled — nothing to do.');

            return self::SUCCESS;
        }

        $user = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->orderBy('id')->first();
        if (! $user) {
            $this->error('No admin user to attribute the run to.');

            return self::FAILURE;
        }

        $result = DunningService::run($user, $this->option('as-of'));
        $this->info("Dunning: sent {$result['sent']} reminder(s), {$result['emailed']} emailed.");

        return self::SUCCESS;
    }
}
