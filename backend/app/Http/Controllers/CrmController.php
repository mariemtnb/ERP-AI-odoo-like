<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\CrmStage;
use App\Models\Lead;
use App\Services\CrmService;
use Illuminate\Http\Request;

/** CRM pipeline: stages, moving leads, win/loss and the weighted forecast. */
class CrmController extends Controller
{
    /** The configured pipeline stages, in order. */
    public function stages()
    {
        return response()->json(
            CrmStage::where('is_active', true)->orderBy('sequence')->get()->map(fn ($s) => $s->toApi())->all()
        );
    }

    /** Move a lead to a stage (a reason is required for the lost stage). */
    public function move(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'stage_id' => ['required', 'integer', 'exists:crm_stages,id'],
            'lost_reason' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);
        $stage = CrmStage::findOrFail($data['stage_id']);

        try {
            $lead = CrmService::moveToStage($lead, $stage, $data['lost_reason'] ?? null);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($lead->toApi());
    }

    /** The open pipeline grouped by stage, with a probability-weighted forecast. */
    public function pipeline()
    {
        return response()->json(CrmService::pipeline());
    }
}
