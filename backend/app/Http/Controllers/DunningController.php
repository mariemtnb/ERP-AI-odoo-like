<?php

namespace App\Http\Controllers;

use App\Models\DunningLevel;
use App\Services\DunningService;
use Illuminate\Http\Request;

/** AR dunning: preview and send follow-ups on overdue invoices (managers/admins). */
class DunningController extends Controller
{
    /** The configured reminder ladder. */
    public function levels()
    {
        return response()->json(
            DunningLevel::orderBy('level')->get()->map(fn ($l) => $l->toApi())->all()
        );
    }

    /** Overdue invoices with a reminder due but not yet sent. */
    public function candidates(Request $request)
    {
        $data = $request->validate(['as_of' => ['sometimes', 'nullable', 'date']]);

        $rows = array_map(fn ($c) => [
            'sale_id' => $c['sale']->id,
            'sale_number' => $c['sale']->number,
            'customer_name' => $c['sale']->customer?->name,
            'customer_email' => $c['sale']->customer?->email,
            'days_overdue' => $c['days_overdue'],
            'outstanding' => number_format($c['outstanding'], 3, '.', ''),
            'level' => $c['level']->level,
            'level_name' => $c['level']->name,
        ], DunningService::candidates($data['as_of'] ?? null));

        return response()->json(['count' => count($rows), 'candidates' => $rows]);
    }

    /** Send every due reminder. */
    public function run(Request $request)
    {
        $data = $request->validate(['as_of' => ['sometimes', 'nullable', 'date']]);

        return response()->json(DunningService::run($request->user(), $data['as_of'] ?? null));
    }
}
