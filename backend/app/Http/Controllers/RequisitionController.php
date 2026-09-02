<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\PurchaseRequisition;
use App\Services\RequisitionService;
use Illuminate\Http\Request;

/** Purchase requisitions: raise, submit for approval, convert to a PO. */
class RequisitionController extends Controller
{
    public function index()
    {
        return response()->json(
            PurchaseRequisition::with(['lines', 'supplier'])->orderByDesc('id')->get()
                ->map(fn ($r) => $r->toApi())->all()
        );
    }

    public function show(PurchaseRequisition $requisition)
    {
        return response()->json(
            $requisition->load(['lines.product', 'supplier', 'approvalRequest.actions'])->toApi()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.estimated_price' => ['sometimes', 'nullable', 'numeric', 'gte:0'],
            'lines.*.notes' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $req = RequisitionService::create($data, $data['lines'], $request->user());

        return response()->json($req->load('lines.product', 'supplier')->toApi(), 201);
    }

    public function submit(Request $request, PurchaseRequisition $requisition)
    {
        try {
            $requisition = RequisitionService::submit($requisition, $request->user());
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($requisition->load('lines', 'approvalRequest.actions')->toApi());
    }

    public function convert(Request $request, PurchaseRequisition $requisition)
    {
        try {
            $po = RequisitionService::convert($requisition, $request->user());
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json([
            'detail' => 'Purchase order created.',
            'purchase_order_id' => $po->id,
            'purchase_order_number' => $po->number,
        ], 201);
    }
}
