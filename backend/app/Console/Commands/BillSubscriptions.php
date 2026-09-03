<?php

namespace App\Console\Commands;

use App\Models\FeatureFlag;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * `php artisan subscriptions:bill`
 *
 * Generates an invoice for every active subscription whose next invoice is due,
 * catching up any missed periods. Meant to run daily; it is idempotent — a
 * period already invoiced is skipped.
 */
class BillSubscriptions extends Command
{
    protected $signature = 'subscriptions:bill {--as-of= : Billing date (default today)}';

    protected $description = 'Generate invoices for due subscriptions';

    public function handle(): int
    {
        if (! FeatureFlag::enabled('subscriptions')) {
            $this->warn('Subscriptions module is disabled — nothing to do.');

            return self::SUCCESS;
        }

        $generated = SubscriptionService::runBilling($this->option('as-of'));
        $this->info('Subscriptions: generated '.count($generated).' invoice(s).');

        return self::SUCCESS;
    }
}
