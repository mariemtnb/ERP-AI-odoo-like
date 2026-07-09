<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\DocumentService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    private const WITH = ['supplier', 'creator', 'lines.product'];

    public function index(Request $request)
    {
        $query = PurchaseOrder::with(self::WITH)->orderByDesc('created_at')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($supplier = $request->query('supplier')) {
            $query->where('supplier_id', $supplier);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('number', 'ilike', "%{$search}%")
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'ilike', "%{$search}%")));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (PurchaseOrder $po) => $po->toApi())
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier' => ['required', 'integer', 'exists:suppliers,id'],
            'order_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $po = DB::transaction(function () use ($data, $request) {
            $po = PurchaseOrder::create([
                'number' => DocumentService::nextNumber('PO', PurchaseOrder::class),
                'supplier_id' => $data['supplier'],
                'order_date' => $data['order_date'],
                'created_by' => $request->user()->id,
            ]);
            foreach ($data['lines'] as $line) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $line['product'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                ]);
            }
            $po->load('lines')->recomputeTotal();

            return $po->refresh();
        });

        return response()->json($po->load(self::WITH)->toApi(), 201);
    }

    public function show(PurchaseOrder $purchase)
    {
        return response()->json($purchase->load(self::WITH)->toApi());
    }

    public function update(Request $request, PurchaseOrder $purchase)
    {
        if ($purchase->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json(['detail' => 'Only draft orders can be edited.'], 400);
        }
        $data = $this->validated($request);

        DB::transaction(function () use ($purchase, $data) {
            $purchase->update([
                'supplier_id' => $data['supplier'],
                'order_date' => $data['order_date'],
            ]);
            $purchase->lines()->delete();
            foreach ($data['lines'] as $line) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $purchase->id,
                    'product_id' => $line['product'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                ]);
            }
            $purchase->load('lines')->recomputeTotal();
        });

        return response()->json($purchase->load(self::WITH)->toApi());
    }

    public function destroy()
    {
        return response()->json(
            ['detail' => 'Purchase orders cannot be deleted — cancel them instead.'],
            405
        );
    }

    private function transition(PurchaseOrder $purchase, callable $fn)
    {
        try {
            $fn($purchase);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($purchase->load(self::WITH)->toApi());
    }

    public function confirm(PurchaseOrder $purchase)
    {
        return $this->transition($purchase, fn ($po) => DocumentService::confirmPurchase($po));
    }

    public function receive(Request $request, PurchaseOrder $purchase)
    {
        return $this->transition(
            $purchase,
            fn ($po) => DocumentService::receivePurchase($po, $request->user())
        );
    }

    public function cancel(PurchaseOrder $purchase)
    {
        return $this->transition($purchase, fn ($po) => DocumentService::cancelPurchase($po));
    }
}
