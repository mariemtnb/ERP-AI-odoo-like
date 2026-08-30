<?php

namespace App\Http\Controllers;

use App\Models\ReorderRule;
use App\Services\ReorderService;
use Illuminate\Http\Request;

class ReorderController extends Controller
{
    public function index()
    {
        $rules = ReorderRule::with(['product', 'supplier'])->orderBy('id')->get();

        return response()->json(['results' => $rules->map(fn (ReorderRule $r) => $r->toApi())->values()]);
    }

    public function suggestions()
    {
        return response()->json(['results' => ReorderService::suggestions()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:products,id', 'unique:reorder_rules,product_id'],
            'supplier' => ['nullable', 'integer', 'exists:suppliers,id'],
            'min_qty' => ['required', 'numeric', 'min:0'],
            'reorder_qty' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule = ReorderRule::create([
            'product_id' => $data['product'],
            'supplier_id' => $data['supplier'] ?? null,
            'min_qty' => $data['min_qty'],
            'reorder_qty' => $data['reorder_qty'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($rule->load(['product', 'supplier'])->toApi(), 201);
    }

    public function update(Request $request, ReorderRule $reorderRule)
    {
        $data = $request->validate([
            'supplier' => ['nullable', 'integer', 'exists:suppliers,id'],
            'min_qty' => ['sometimes', 'numeric', 'min:0'],
            'reorder_qty' => ['sometimes', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('supplier', $data)) {
            $reorderRule->supplier_id = $data['supplier'];
        }
        foreach (['min_qty', 'reorder_qty', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $reorderRule->{$f} = $data[$f];
            }
        }
        $reorderRule->save();

        return response()->json($reorderRule->load(['product', 'supplier'])->toApi());
    }

    public function destroy(ReorderRule $reorderRule)
    {
        $reorderRule->delete();

        return response()->json(null, 204);
    }

    public function run(Request $request)
    {
        $result = ReorderService::generateDraftPurchaseOrders($request->user());

        return response()->json([
            'created' => array_map(fn ($po) => [
                'id' => $po->id,
                'number' => $po->number,
                'supplier' => $po->supplier_id,
                'total_amount' => $po->total_amount,
                'lines' => $po->lines->count(),
            ], $result['orders']),
            'unassigned' => $result['unassigned'],
        ]);
    }
}
