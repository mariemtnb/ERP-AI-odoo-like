<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\FixedAsset;
use App\Services\AssetService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $query = FixedAsset::orderByDesc('acquisition_date')->orderByDesc('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(DrfPagination::paginate($query, $request, fn (FixedAsset $a) => $a->toApi()));
    }

    public function show(FixedAsset $asset)
    {
        return response()->json($asset->toApi() + [
            'monthly_charge' => AssetService::monthlyCharge($asset),
            'entries' => $asset->entries()->orderBy('period')->get()->map(fn ($e) => $e->toApi())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'gt:0'],
        ]);

        $asset = FixedAsset::create([
            'name' => $data['name'],
            'category' => $data['category'] ?? '',
            'acquisition_date' => $data['acquisition_date'],
            'acquisition_cost' => $data['acquisition_cost'],
            'salvage_value' => $data['salvage_value'] ?? 0,
            'useful_life_months' => $data['useful_life_months'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json($asset->toApi(), 201);
    }

    public function schedule(FixedAsset $asset)
    {
        return response()->json([
            'monthly_charge' => AssetService::monthlyCharge($asset),
            'schedule' => AssetService::schedule($asset),
        ]);
    }

    public function depreciate(Request $request, FixedAsset $asset)
    {
        $data = $request->validate(['period' => ['nullable', 'date']]);

        try {
            $entry = AssetService::depreciate($asset, $data['period'] ?? now()->toDateString(), $request->user());
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($asset->fresh()->toApi() + ['entry' => $entry->toApi()], 201);
    }

    public function dispose(Request $request, FixedAsset $asset)
    {
        $data = $request->validate(['disposed_date' => ['nullable', 'date']]);

        try {
            AssetService::dispose($asset, $data['disposed_date'] ?? null);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($asset->fresh()->toApi());
    }
}
