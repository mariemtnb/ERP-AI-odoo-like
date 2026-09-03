<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\CrmStage;
use App\Models\Lead;

/**
 * The sales pipeline. Leads move through stages; moving to a won or lost stage
 * closes the opportunity (a lost one records why). The pipeline can be forecast
 * weighted by each open lead's win probability.
 */
class CrmService
{
    /** Move a lead to a stage, syncing its status and probability. */
    public static function moveToStage(Lead $lead, CrmStage $stage, ?string $lostReason = null): Lead
    {
        if ($stage->is_lost && ($lostReason === null || trim($lostReason) === '')) {
            throw new InvalidTransition('A reason is required when marking a lead lost.');
        }

        $lead->stage_id = $stage->id;
        $lead->probability = null;                 // fall back to the new stage's default
        if ($stage->is_won) {
            $lead->status = 'won';
            $lead->lost_reason = '';
        } elseif ($stage->is_lost) {
            $lead->status = 'lost';
            $lead->lost_reason = $lostReason;
        } else {
            $lead->status = 'qualified';
            $lead->lost_reason = '';
        }
        $lead->save();

        return $lead->refresh()->load('stage');
    }

    /** Open opportunities grouped by stage, with a probability-weighted forecast. */
    public static function pipeline(): array
    {
        $stages = CrmStage::where('is_active', true)->orderBy('sequence')->get();
        $leads = Lead::with('stage')->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))->get();

        $byStage = [];
        foreach ($stages->where('is_won', false)->where('is_lost', false) as $stage) {
            $byStage[$stage->id] = [
                'stage_id' => $stage->id, 'stage_name' => $stage->name, 'sequence' => $stage->sequence,
                'count' => 0, 'expected_revenue' => 0.0, 'weighted_value' => 0.0,
            ];
        }

        foreach ($leads as $lead) {
            $row = &$byStage[$lead->stage_id];
            $row['count']++;
            $row['expected_revenue'] += (float) $lead->expected_revenue;
            $row['weighted_value'] += $lead->weightedValue();
        }
        unset($row);

        $rows = array_map(function ($r) {
            $r['expected_revenue'] = round($r['expected_revenue'], 3);
            $r['weighted_value'] = round($r['weighted_value'], 3);

            return $r;
        }, array_values($byStage));

        return [
            'title' => 'Sales pipeline',
            'stages' => $rows,
            'open_count' => (int) array_sum(array_column($rows, 'count')),
            'total_expected' => round(array_sum(array_column($rows, 'expected_revenue')), 3),
            'total_weighted' => round(array_sum(array_column($rows, 'weighted_value')), 3),
        ];
    }
}
