<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\BillOfMaterials;
use App\Models\BomComponent;
use App\Models\WorkOrder;
use App\Services\ManufacturingService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManufacturingController extends Controller
{
    // ---------- bills of materials ----------

    public function bomIndex()
    {
        $boms = BillOfMaterials::with(['product', 'components.component'])->orderBy('id')->get();

        return response()->json(['results' => $boms->map(fn (BillOfMaterials $b) => $b->toApi())->values()]);
    }

    public function bomShow(BillOfMaterials $bom)
    {
        return response()->json($bom->load(['product', 'components.component'])->toApi());
    }

    public function bomStore(Request $request)
    {
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:products,id', 'unique:bills_of_materials,product_id'],
            'output_quantity' => ['nullable', 'numeric', 'gt:0'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.component' => ['required', 'integer', 'exists:products,id', 'different:product'],
            'components.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $bom = DB::transaction(function () use ($data, $request) {
            $bom = BillOfMaterials::create([
                'product_id' => $data['product'],
                'output_quantity' => $data['output_quantity'] ?? 1,
                'created_by' => $request->user()->id,
            ]);
            foreach ($data['components'] as $c) {
                BomComponent::create([
                    'bom_id' => $bom->id,
                    'component_product_id' => $c['component'],
                    'quantity' => $c['quantity'],
                ]);
            }

            return $bom;
        });

        return response()->json($bom->load(['product', 'components.component'])->toApi(), 201);
    }

    // ---------- work orders ----------

    public function woIndex(Request $request)
    {
        $query = WorkOrder::with(['product', 'creator'])->orderByDesc('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(DrfPagination::paginate($query, $request, fn (WorkOrder $w) => $w->toApi()));
    }

    public function woShow(WorkOrder $workOrder)
    {
        $bom = $workOrder->bom()->with('components.component')->first();

        return response()->json($workOrder->load(['product', 'creator'])->toApi() + [
            'requirements' => $bom ? ManufacturingService::requirements($bom, (float) $workOrder->quantity) : [],
        ]);
    }

    public function woStore(Request $request)
    {
        $data = $request->validate([
            'bom' => ['required', 'integer', 'exists:bills_of_materials,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $bom = BillOfMaterials::with('components')->findOrFail($data['bom']);

        return $this->guard(fn () => ManufacturingService::createWorkOrder($bom, (float) $data['quantity'], $request->user())
            ->load(['product', 'creator'])->toApi(), 201);
    }

    public function woAction(Request $request, WorkOrder $workOrder, string $action)
    {
        return $this->guard(function () use ($workOrder, $action, $request) {
            $wo = match ($action) {
                'start' => ManufacturingService::start($workOrder),
                'complete' => ManufacturingService::complete($workOrder, $request->user()),
                'cancel' => ManufacturingService::cancel($workOrder),
            };

            return $wo->load(['product', 'creator'])->toApi();
        });
    }

    private function guard(callable $fn, int $ok = 200)
    {
        try {
            return response()->json($fn(), $ok);
        } catch (InvalidTransition|InsufficientStock $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }
    }
}
