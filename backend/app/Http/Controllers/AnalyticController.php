<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\JournalEntryLine;
use App\Services\AnalyticService;
use Illuminate\Http\Request;

/** Analytic accounting: cost-centre tagging and per-dimension P&L. */
class AnalyticController extends Controller
{
    /** P&L per business unit over an optional date range. */
    public function pnl(Request $request)
    {
        $data = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
        ]);

        return response()->json(AnalyticService::pnl($data['from'] ?? null, $data['to'] ?? null));
    }

    /** Assign (or clear) the business unit on a posted ledger line. */
    public function tagLine(Request $request, JournalEntryLine $line)
    {
        $data = $request->validate([
            'business_unit_id' => ['present', 'nullable', 'integer'],
        ]);

        try {
            $line = AnalyticService::tag($line, $data['business_unit_id']);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($line->load('account', 'businessUnit')->toApi());
    }
}
