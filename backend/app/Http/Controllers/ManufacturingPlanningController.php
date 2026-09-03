<?php

namespace App\Http\Controllers;

use App\Models\BillOfMaterials;
use App\Models\Product;
use App\Models\WorkCentre;
use App\Services\MrpService;
use App\Services\RoutingService;
use Illuminate\Http\Request;

/** Work centres, BOM routings, routing cost and MRP. */
class ManufacturingPlanningController extends Controller
{
    // ---- work centres ----

    public function workCentres()
    {
        return response()->json(
            WorkCentre::where('is_active', true)->orderBy('code')->get()->map(fn ($w) => $w->toApi())->all()
        );
    }

    public function storeWorkCentre(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:work_centres,code'],
            'name' => ['required', 'string', 'max:120'],
            'cost_per_hour' => ['required', 'numeric', 'min:0'],
            'capacity_minutes_per_day' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json(WorkCentre::create($data)->toApi(), 201);
    }

    // ---- routing ----

    public function addOperation(Request $request, BillOfMaterials $bom)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
            'work_centre_id' => ['sometimes', 'nullable', 'integer', 'exists:work_centres,id'],
            'minutes' => ['required', 'numeric', 'min:0'],
        ]);

        $op = $bom->operations()->create($data);

        return response()->json($op->load('workCentre')->toApi(), 201);
    }

    /** The routing cost and time to build a quantity from this BOM. */
    public function routingCost(Request $request, BillOfMaterials $bom)
    {
        $qty = (float) $request->query('qty', (string) $bom->output_quantity);

        return response()->json(RoutingService::cost($bom, $qty > 0 ? $qty : (float) $bom->output_quantity));
    }

    // ---- MRP ----

    /** Explode a product's BOM for a quantity, netting against stock on hand. */
    public function mrp(Request $request, Product $product)
    {
        $data = $request->validate(['qty' => ['required', 'numeric', 'gt:0']]);

        return response()->json(MrpService::explode($product, (float) $data['qty']));
    }
}
