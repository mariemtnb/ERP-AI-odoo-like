<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Rfq;
use App\Models\RfqBid;
use App\Services\RfqService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class RfqController extends Controller
{
    public function index(Request $request)
    {
        $query = Rfq::with('lines.product')->orderByDesc('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(DrfPagination::paginate($query, $request, fn (Rfq $r) => $r->toApi()));
    }

    public function show(Rfq $rfq)
    {
        return response()->json($rfq->load('lines.product')->toApi() + [
            'comparison' => RfqService::compare($rfq),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'due_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        return $this->guard(fn () => RfqService::createRfq(
            $data['title'], $data['due_date'] ?? null, $data['lines'], $request->user()
        )->load('lines.product')->toApi(), 201);
    }

    public function submitBid(Request $request, Rfq $rfq)
    {
        $data = $request->validate([
            'supplier' => ['required', 'integer', 'exists:suppliers,id'],
            'note' => ['nullable', 'string', 'max:255'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*' => ['required', 'numeric', 'min:0'],
        ]);

        // keys are rfq_line ids (strings from JSON) -> cast to int
        $prices = [];
        foreach ($data['prices'] as $lineId => $price) {
            $prices[(int) $lineId] = $price;
        }

        return $this->guard(fn () => RfqService::submitBid(
            $rfq, $data['supplier'], $prices, $data['note'] ?? '', $request->user()
        )->toApi(), 201);
    }

    public function compare(Rfq $rfq)
    {
        return response()->json(['results' => RfqService::compare($rfq)]);
    }

    public function award(Request $request, Rfq $rfq, RfqBid $bid)
    {
        return $this->guard(function () use ($rfq, $bid, $request) {
            $po = RfqService::award($rfq, $bid, $request->user());

            return ['awarded_bid' => $bid->id, 'purchase_order' => $po->number, 'total_amount' => $po->total_amount];
        });
    }

    private function guard(callable $fn, int $ok = 200)
    {
        try {
            return response()->json($fn(), $ok);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }
    }
}
