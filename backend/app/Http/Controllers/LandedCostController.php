<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Exceptions\UnbalancedEntry;
use App\Models\LandedCost;
use App\Models\PurchaseOrder;
use App\Services\LandedCostService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Landed costs on a purchase order's goods receipt (managers/admins). */
class LandedCostController extends Controller
{
    public function index(PurchaseOrder $purchase)
    {
        return response()->json(
            LandedCost::where('purchase_order_id', $purchase->id)
                ->with('allocations.product')->orderByDesc('id')->get()
                ->map(fn ($l) => $l->toApi())->all()
        );
    }

    public function store(Request $request, PurchaseOrder $purchase)
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'allocation' => ['sometimes', Rule::in(LandedCost::ALLOCATIONS)],
        ]);

        try {
            $landed = LandedCostService::apply($purchase, $data, $request->user());
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($landed->toApi(), 201);
    }
}
