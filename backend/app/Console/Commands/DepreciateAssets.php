<?php

namespace App\Console\Commands;

use App\Exceptions\InvalidTransition;
use App\Models\FeatureFlag;
use App\Models\FixedAsset;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Console\Command;

/**
 * `php artisan assets:depreciate`
 *
 * Posts the monthly straight-line depreciation for every active fixed asset.
 * Meant to run once a month; an asset already depreciated for the period (or
 * fully depreciated) is skipped, so a repeat run posts nothing extra.
 */
class DepreciateAssets extends Command
{
    protected $signature = 'assets:depreciate {--period= : Month to post (Y-m, default this month)}';

    protected $description = 'Post monthly depreciation for active fixed assets';

    public function handle(): int
    {
        if (! FeatureFlag::enabled('assets')) {
            $this->warn('Assets module is disabled — nothing to do.');

            return self::SUCCESS;
        }

        $user = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->orderBy('id')->first();
        if (! $user) {
            $this->error('No admin user to attribute the run to.');

            return self::FAILURE;
        }

        $period = $this->option('period') ?: now()->format('Y-m');
        $posted = 0;
        $skipped = 0;

        foreach (FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)->get() as $asset) {
            try {
                AssetService::depreciate($asset, $period, $user);
                $posted++;
            } catch (InvalidTransition) {
                // Already posted this period, or fully depreciated — expected.
                $skipped++;
            }
        }

        $this->info("Depreciation ({$period}): posted {$posted}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
