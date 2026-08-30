<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\DepreciationEntry;
use App\Models\FixedAsset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Straight-line depreciation of fixed assets. The monthly charge is the
 * depreciable base (cost minus salvage) divided by the useful life in months;
 * the final run is trimmed so accumulated depreciation never overshoots the
 * base and the book value settles exactly at the salvage value.
 */
class AssetService
{
    public static function monthlyCharge(FixedAsset $asset): float
    {
        if ($asset->useful_life_months <= 0) {
            return 0.0;
        }

        return round($asset->depreciableBase() / $asset->useful_life_months, 2);
    }

    /** Depreciate one month. $period is any date in the target month. */
    public static function depreciate(FixedAsset $asset, string $period, User $user): DepreciationEntry
    {
        if ($asset->status !== FixedAsset::STATUS_ACTIVE) {
            throw new InvalidTransition('Only active assets can be depreciated.');
        }
        if ($asset->isFullyDepreciated()) {
            throw new InvalidTransition('Asset is already fully depreciated.');
        }

        $month = Carbon::parse($period)->startOfMonth()->toDateString();
        if (DepreciationEntry::where('fixed_asset_id', $asset->id)->whereDate('period', $month)->exists()) {
            throw new InvalidTransition("Already depreciated for {$month}.");
        }

        return DB::transaction(function () use ($asset, $month, $user) {
            $asset = FixedAsset::lockForUpdate()->find($asset->id);
            $remaining = round($asset->depreciableBase() - (float) $asset->accumulated_depreciation, 2);
            $amount = min(self::monthlyCharge($asset), $remaining);

            $asset->accumulated_depreciation = (float) $asset->accumulated_depreciation + $amount;
            $asset->save();

            return DepreciationEntry::create([
                'fixed_asset_id' => $asset->id,
                'period' => $month,
                'amount' => $amount,
                'book_value_after' => $asset->bookValue(),
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * Projected remaining depreciation schedule from today's book value.
     *
     * @return array<int,array{month:int,amount:float,book_value_after:float}>
     */
    public static function schedule(FixedAsset $asset): array
    {
        $charge = self::monthlyCharge($asset);
        $accumulated = (float) $asset->accumulated_depreciation;
        $base = $asset->depreciableBase();
        $rows = [];
        $i = 0;
        while ($accumulated + 0.001 < $base && $i < 1200) {
            $i++;
            $amount = min($charge, round($base - $accumulated, 2));
            $accumulated = round($accumulated + $amount, 2);
            $rows[] = [
                'month' => $i,
                'amount' => $amount,
                'book_value_after' => round((float) $asset->acquisition_cost - $accumulated, 2),
            ];
        }

        return $rows;
    }

    public static function dispose(FixedAsset $asset, ?string $date): FixedAsset
    {
        if ($asset->status === FixedAsset::STATUS_DISPOSED) {
            throw new InvalidTransition('Asset is already disposed.');
        }
        $asset->update([
            'status' => FixedAsset::STATUS_DISPOSED,
            'disposed_date' => $date ?? now()->toDateString(),
        ]);

        return $asset;
    }
}
