<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\StockLot;
use App\Services\LotService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class LotController extends Controller
{
    private const WITH = ['product', 'warehouse'];

    public function index(Request $request)
    {
        $query = StockLot::with(self::WITH)->orderByRaw('expiry_date asc nulls last')->orderBy('id');

        if ($product = $request->query('product')) {
            $query->where('product_id', $product);
        }
        if ($warehouse = $request->query('warehouse')) {
            $query->where('warehouse_id', $warehouse);
        }
        if ($request->boolean('in_stock')) {
            $query->where('quantity', '>', 0);
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (StockLot $l) => $l->toApi())
        );
    }

    /** Expiry dashboard: expired + expiring-soon lots. */
    public function alerts(Request $request)
    {
        $days = (int) $request->query('days', 7);

        return response()->json([
            'expired' => LotService::expired()->map(fn (StockLot $l) => $l->toApi())->values(),
            'expiring' => LotService::expiring($days)->map(fn (StockLot $l) => $l->toApi())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:products,id'],
            'warehouse' => ['nullable', 'integer', 'exists:warehouses,id'],
            'lot_number' => ['required', 'string', 'max:64'],
            'expiry_date' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $lot = LotService::receive(
                $data['product'],
                $data['warehouse'] ?? null,
                $data['lot_number'],
                $data['expiry_date'] ?? null,
                (float) $data['quantity'],
                $request->user(),
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($lot->toApi(), 201);
    }

    public function consume(Request $request)
    {
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:products,id'],
            'warehouse' => ['nullable', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $taken = LotService::consumeFefo(
                $data['product'],
                $data['warehouse'] ?? null,
                (float) $data['quantity'],
                $request->user(),
                $data['reason'] ?? '',
            );
        } catch (InvalidTransition|InsufficientStock $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json(['consumed' => $taken]);
    }
}
