<?php

namespace App\Services;

use App\Models\BillOfMaterials;

/**
 * Routing cost and time. Scales a BOM's operations from its output batch to the
 * quantity being produced and prices the time at each work centre's hourly rate.
 */
class RoutingService
{
    public static function cost(BillOfMaterials $bom, float $qty): array
    {
        $bom->loadMissing('operations.workCentre');
        $output = max(0.000001, (float) $bom->output_quantity);
        $scale = $qty / $output;

        $rows = [];
        $totalMinutes = 0.0;
        $labour = 0.0;
        foreach ($bom->operations as $op) {
            $minutes = round((float) $op->minutes * $scale, 2);
            $rate = (float) ($op->workCentre?->cost_per_hour ?? 0);
            $cost = round($minutes / 60 * $rate, 3);
            $totalMinutes += $minutes;
            $labour += $cost;
            $rows[] = [
                'sequence' => $op->sequence,
                'name' => $op->name,
                'work_centre' => $op->workCentre?->name,
                'minutes' => $minutes,
                'cost' => $cost,
            ];
        }

        return [
            'bom_id' => $bom->id,
            'quantity' => $qty,
            'operations' => $rows,
            'total_minutes' => round($totalMinutes, 2),
            'labour_cost' => round($labour, 3),
        ];
    }
}
