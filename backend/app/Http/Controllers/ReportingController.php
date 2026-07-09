<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Dashboard stats and reports — same payloads as the Django reporting app. */
class ReportingController extends Controller
{
    private static function range(Request $request): array
    {
        $from = date_create($request->query('from', '')) ?: now()->subDays(30);
        $to = date_create($request->query('to', '')) ?: now();

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }

    public function dashboard(Request $request)
    {
        [$from, $to] = self::range($request);

        $sales = Sale::where('status', Sale::STATUS_CONFIRMED)
            ->whereBetween('sale_date', [$from, $to]);
        $purchases = PurchaseOrder::where('status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->whereBetween('order_date', [$from, $to]);

        $topProducts = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->where('sales.status', Sale::STATUS_CONFIRMED)
            ->whereBetween('sales.sale_date', [$from, $to])
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderByDesc(DB::raw('SUM(sale_lines.quantity)'))
            ->limit(5)
            ->get([
                'products.id as product__id',
                'products.sku as product__sku',
                'products.name as product__name',
                DB::raw('SUM(sale_lines.quantity) as quantity_sold'),
                DB::raw('SUM(sale_lines.quantity * sale_lines.unit_price) as revenue'),
            ]);

        $lowStock = Product::where('is_active', true)
            ->whereColumn('quantity_in_stock', '<=', 'min_stock_level')
            ->get(['id', 'sku', 'name', 'quantity_in_stock', 'min_stock_level']);

        return response()->json([
            'date_from' => $from,
            'date_to' => $to,
            'revenue' => (float) $sales->clone()->sum('total_amount'),
            'sales_count' => $sales->clone()->count(),
            'purchases_count' => $purchases->clone()->count(),
            'purchases_amount' => (float) $purchases->clone()->sum('total_amount'),
            'top_products' => $topProducts->map(fn ($p) => [
                'product__id' => $p->product__id,
                'product__sku' => $p->product__sku,
                'product__name' => $p->product__name,
                'quantity_sold' => (float) $p->quantity_sold,
                'revenue' => (float) $p->revenue,
            ])->values()->all(),
            'low_stock' => $lowStock->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'quantity_in_stock' => $p->quantity_in_stock,
                'min_stock_level' => $p->min_stock_level,
            ])->values()->all(),
        ]);
    }

    private function respond(Request $request, array $data)
    {
        if ($request->query('export') === 'pdf') {
            $slug = str_replace(' ', '_', strtolower($data['title']));
            $pdf = Pdf::loadView('reports.report', $data);

            return $pdf->download("{$slug}.pdf");
        }

        return response()->json($data);
    }

    public function salesReport(Request $request)
    {
        [$from, $to] = self::range($request);
        $rows = Sale::with('customer')
            ->where('status', '!=', Sale::STATUS_DRAFT)
            ->whereBetween('sale_date', [$from, $to])
            ->orderBy('sale_date')->orderBy('number')->get()
            ->map(fn (Sale $s) => [
                'number' => $s->number,
                'date' => $s->sale_date->format('Y-m-d'),
                'customer' => $s->customer->name,
                'status' => $s->status,
                'total' => (float) $s->total_amount,
            ]);
        $confirmed = $rows->where('status', Sale::STATUS_CONFIRMED);

        return $this->respond($request, [
            'title' => 'Sales report',
            'date_from' => $from,
            'date_to' => $to,
            'rows' => $rows->values()->all(),
            'count' => $confirmed->count(),
            'total' => (float) $confirmed->sum('total'),
        ]);
    }

    public function purchasesReport(Request $request)
    {
        [$from, $to] = self::range($request);
        $rows = PurchaseOrder::with('supplier')
            ->where('status', '!=', PurchaseOrder::STATUS_DRAFT)
            ->whereBetween('order_date', [$from, $to])
            ->orderBy('order_date')->orderBy('number')->get()
            ->map(fn (PurchaseOrder $p) => [
                'number' => $p->number,
                'date' => $p->order_date->format('Y-m-d'),
                'customer' => $p->supplier->name, // column shared with sales template
                'status' => $p->status,
                'total' => (float) $p->total_amount,
            ]);
        $active = $rows->where('status', '!=', PurchaseOrder::STATUS_CANCELLED);

        return $this->respond($request, [
            'title' => 'Purchases report',
            'date_from' => $from,
            'date_to' => $to,
            'rows' => $rows->values()->all(),
            'count' => $active->count(),
            'total' => (float) $active->sum('total'),
        ]);
    }

    public function stockReport(Request $request)
    {
        $rows = Product::with('category')
            ->where('is_active', true)
            ->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category?->name ?? '—',
                'quantity' => $p->quantity_in_stock,
                'min_level' => $p->min_stock_level,
                'value' => (float) $p->quantity_in_stock * (float) $p->cost_price,
                'low' => $p->isLowStock(),
            ]);

        return $this->respond($request, [
            'title' => 'Stock report',
            'rows' => $rows->values()->all(),
            'count' => $rows->count(),
            'total' => (float) $rows->sum('value'),
        ]);
    }
}
