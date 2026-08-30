<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\SavedReport;
use App\Services\BiService;
use Illuminate\Http\Request;

class BiController extends Controller
{
    public function run(Request $request)
    {
        $data = $request->validate([
            'source' => ['nullable', 'string'],
            'group_by' => ['required', 'string', 'in:month,customer,product'],
            'measure' => ['required', 'string', 'in:total,count'],
        ]);

        try {
            $rows = BiService::run($data['source'] ?? 'sales', $data['group_by'], $data['measure']);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json([
            'group_by' => $data['group_by'],
            'measure' => $data['measure'],
            'rows' => $rows,
            'total' => round(array_sum(array_map(fn ($r) => $r['value'], $rows)), 2),
        ]);
    }

    public function index()
    {
        $reports = SavedReport::with('creator')->orderBy('name')->get();

        return response()->json(['results' => $reports->map(fn (SavedReport $r) => $r->toApi())->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'group_by' => ['required', 'string', 'in:month,customer,product'],
            'measure' => ['required', 'string', 'in:total,count'],
        ]);

        $report = SavedReport::create([
            'name' => $data['name'],
            'source' => 'sales',
            'group_by' => $data['group_by'],
            'measure' => $data['measure'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json($report->toApi(), 201);
    }

    public function runSaved(SavedReport $report)
    {
        $rows = BiService::run($report->source, $report->group_by, $report->measure);

        return response()->json($report->toApi() + [
            'rows' => $rows,
            'total' => round(array_sum(array_map(fn ($r) => $r['value'], $rows)), 2),
        ]);
    }

    public function destroy(SavedReport $report)
    {
        $report->delete();

        return response()->json(null, 204);
    }
}
