<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStock;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WarehouseController extends Controller
{
    public function index()
    {
        return response()->json([
            'results' => Warehouse::orderByDesc('is_default')->orderBy('name')
                ->get()->map(fn (Warehouse $w) => $w->toApi())->values()->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:warehouses,name'],
            'address' => ['sometimes', 'string'],
        ]);

        return response()->json(Warehouse::create($data)->toApi(), 201);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'address' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (($data['is_active'] ?? true) === false && $warehouse->is_default) {
            return response()->json(['detail' => 'The default warehouse cannot be deactivated.'], 400);
        }
        $warehouse->update($data);

        return response()->json($warehouse->toApi());
    }

    /** Stock breakdown per warehouse, optionally filtered by product. */
    public function stock(Request $request)
    {
        $query = WarehouseStock::with(['warehouse', 'product'])
            ->where('quantity', '!=', 0);
        if ($product = $request->query('product')) {
            $query->where('product_id', $product);
        }

        return response()->json([
            'results' => $query->get()->map(fn (WarehouseStock $s) => [
                'warehouse' => $s->warehouse_id,
                'warehouse_name' => $s->warehouse?->name,
                'product' => $s->product_id,
                'product_sku' => $s->product?->sku,
                'product_name' => $s->product?->name,
                'quantity' => $s->quantity,
            ])->values()->all(),
        ]);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:products,id'],
            'from_warehouse' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['sometimes', 'string', 'max:255'],
        ]);

        try {
            [$out, $in] = StockService::transfer(
                productId: $data['product'],
                fromWarehouseId: $data['from_warehouse'],
                toWarehouseId: $data['to_warehouse'],
                quantity: $data['quantity'],
                user: $request->user(),
                reason: $data['reason'] ?? '',
            );
        } catch (InsufficientStock|InvalidArgumentException $e) {
            return response()->json(['detail' => $e->getMessage()], 400);
        }

        return response()->json([
            'out' => $out->load(['product', 'creator', 'warehouse'])->toApi(),
            'in' => $in->load(['product', 'creator', 'warehouse'])->toApi(),
        ], 201);
    }
}
