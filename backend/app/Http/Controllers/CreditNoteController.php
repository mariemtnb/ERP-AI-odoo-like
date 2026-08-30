<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\CreditNote;
use App\Models\Sale;
use App\Services\CreditNoteService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class CreditNoteController extends Controller
{
    private const WITH = ['lines.product', 'sale', 'customer', 'creator'];

    public function index(Request $request)
    {
        $query = CreditNote::with(self::WITH)->orderByDesc('created_at')->orderByDesc('id');

        if ($sale = $request->query('sale')) {
            $query->where('sale_id', $sale);
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (CreditNote $n) => $n->toApi())
        );
    }

    public function show(CreditNote $creditNote)
    {
        return response()->json($creditNote->load(self::WITH)->toApi());
    }

    /** Remaining returnable quantity per product on a sale. */
    public function returnable(Sale $sale)
    {
        return response()->json([
            'sale' => $sale->number,
            'returnable' => CreditNoteService::returnable($sale->load('lines')),
        ]);
    }

    public function store(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'restock' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $note = CreditNoteService::createFromSale(
                $sale->load('lines'),
                $data['lines'],
                $data['restock'] ?? true,
                $data['reason'] ?? '',
                $request->user(),
            );
        } catch (InvalidTransition|InsufficientStock $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($note->toApi(), 201);
    }
}
