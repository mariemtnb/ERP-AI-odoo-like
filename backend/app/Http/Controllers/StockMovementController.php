<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStock;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Ledger rows can be listed and created — never edited or deleted. */
class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = StockMovement::with(['product', 'creator', 'warehouse'])->orderByDesc('created_at')->orderByDesc('id');

        if ($warehouse = $request->query('warehouse')) {
            $query->where('warehouse_id', $warehouse);
        }

        if ($product = $request->query('product')) {
            $query->where('product_id', $product);
        }
        if ($type = $request->query('movement_type')) {
            $query->where('movement_type', $type);
        }
        if ($ref = $request->query('reference_type')) {
            $query->where('reference_type', $ref);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->whereHas('product', fn ($p) => $p
                    ->where('sku', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%"))
                ->orWhere('reason', 'ilike', "%{$search}%"));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (StockMovement $m) => $m->toApi())
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:products,id'],
            'movement_type' => ['required', Rule::in(['in', 'out', 'adjustment'])],
            'quantity' => ['required', 'numeric'],
            'reason' => ['sometimes', 'string', 'max:255'],
            'warehouse' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
        ]);

        try {
            $movement = StockService::recordMovement(
                productId: $data['product'],
                movementType: $data['movement_type'],
                quantity: $data['quantity'],
                user: $request->user(),
                reason: $data['reason'] ?? '',
                warehouseId: $data['warehouse'] ?? null,
            );
        } catch (InsufficientStock|InvalidArgumentException $e) {
            return response()->json(['quantity' => [$e->getMessage()]], 400);
        }

        return response()->json($movement->load(['product', 'creator'])->toApi(), 201);
    }

    public function show(StockMovement $movement)
    {
        return response()->json($movement->load(['product', 'creator'])->toApi());
    }
}
