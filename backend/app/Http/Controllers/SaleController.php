<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Services\DocumentService;
use App\Support\DrfPagination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    private const WITH = ['customer', 'creator', 'lines.product'];

    public function index(Request $request)
    {
        $query = Sale::with(self::WITH)->orderByDesc('created_at')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customer = $request->query('customer')) {
            $query->where('customer_id', $customer);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('number', 'ilike', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', "%{$search}%")));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (Sale $s) => $s->toApi())
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer' => ['required', 'integer', 'exists:customers,id'],
            'sale_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $sale = DB::transaction(function () use ($data, $request) {
            $sale = Sale::create([
                'number' => DocumentService::nextNumber('SO', Sale::class),
                'customer_id' => $data['customer'],
                'sale_date' => $data['sale_date'],
                'created_by' => $request->user()->id,
            ]);
            foreach ($data['lines'] as $line) {
                SaleLine::create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                ]);
            }
            $sale->load('lines')->recomputeTotal();

            return $sale->refresh();
        });

        return response()->json($sale->load(self::WITH)->toApi(), 201);
    }

    public function show(Sale $sale)
    {
        return response()->json($sale->load(self::WITH)->toApi());
    }

    public function destroy()
    {
        return response()->json(
            ['detail' => 'Sales cannot be deleted — cancel them instead.'],
            405
        );
    }

    private function transition(Sale $sale, callable $fn)
    {
        try {
            $fn($sale);
        } catch (InvalidTransition|InsufficientStock $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($sale->load(self::WITH)->toApi());
    }

    public function confirm(Request $request, Sale $sale)
    {
        return $this->transition($sale, fn ($s) => DocumentService::confirmSale($s, $request->user()));
    }

    public function cancel(Request $request, Sale $sale)
    {
        return $this->transition($sale, fn ($s) => DocumentService::cancelSale($s, $request->user()));
    }

    /** POST: generate (idempotent). GET: download the PDF. */
    public function invoice(Request $request, Sale $sale)
    {
        try {
            $invoice = DocumentService::generateInvoice($sale);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        if ($request->isMethod('post')) {
            return response()->json([
                'number' => $invoice->number,
                'issued_at' => $invoice->issued_at?->toISOString(),
            ]);
        }

        $pdf = Pdf::loadView('reports.invoice', [
            'invoice' => $invoice,
            'sale' => $sale->load(self::WITH),
        ]);

        return $pdf->download("{$invoice->number}.pdf");
    }
}
