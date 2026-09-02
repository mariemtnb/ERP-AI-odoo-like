<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
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

    /**
     * VAT declaration for a period.
     *
     * Output VAT is what was collected on sales; input VAT is what was paid
     * (and is deductible) on purchases received in the period; the difference
     * is what is owed to the treasury (or a credit carried forward). Document
     * totals are treated as VAT-inclusive at the company's configured rate —
     * the usual case for a Tunisian SME, where the price on the invoice already
     * includes the TVA. The rate is a setting, so it moves with the law.
     */
    public function vatReturn(Request $request)
    {
        [$from, $to] = self::range($request);

        // Output VAT: sum the VAT within every line of confirmed sales in the
        // period, each at its own rate — so a mix of 0/7/13/19 % is exact.
        $sales = Sale::with('lines')->where('status', Sale::STATUS_CONFIRMED)
            ->whereBetween('sale_date', [$from, $to])->get();
        [$salesGross, $outputVat, $outputByRate] = self::sumVat($sales);

        // Input VAT: same, over purchases already received (receipt date, else order date).
        $purchases = PurchaseOrder::with('lines')->where('status', PurchaseOrder::STATUS_RECEIVED)
            ->whereBetween(DB::raw('COALESCE(received_date, order_date)'), [$from, $to])->get();
        [$purchasesGross, $inputVat, $inputByRate] = self::sumVat($purchases);

        $net = round($outputVat - $inputVat, 2);

        return response()->json([
            'title' => 'VAT return',
            'date_from' => $from,
            'date_to' => $to,
            'rate' => (float) (CompanyProfile::current()->default_vat_rate ?? 19), // default, for reference
            'sales_gross' => round($salesGross, 2),
            'sales_net' => round($salesGross - $outputVat, 2),
            'output_vat' => round($outputVat, 2),
            'output_by_rate' => $outputByRate,
            'purchases_gross' => round($purchasesGross, 2),
            'purchases_net' => round($purchasesGross - $inputVat, 2),
            'input_vat' => round($inputVat, 2),
            'input_by_rate' => $inputByRate,
            'net_vat_due' => max(0.0, $net),
            'vat_credit' => max(0.0, -$net),
        ]);
    }

    /**
     * Total gross, total VAT, and a per-rate breakdown over a set of documents
     * whose lines expose subtotal() and vatAmount().
     *
     * @return array{0: float, 1: float, 2: array<int,array{rate: float, base: float, vat: float}>}
     */
    private static function sumVat($documents): array
    {
        $gross = 0.0;
        $vat = 0.0;
        $byRate = [];   // rate => [base, vat]
        foreach ($documents as $doc) {
            foreach ($doc->lines as $line) {
                $sub = $line->subtotal();
                $v = $line->vatAmount();
                $rate = (float) $line->tax_rate;
                $gross += $sub;
                $vat += $v;
                $byRate[(string) $rate] ??= ['base' => 0.0, 'vat' => 0.0];
                $byRate[(string) $rate]['base'] += $sub - $v;
                $byRate[(string) $rate]['vat'] += $v;
            }
        }

        $rows = [];
        foreach ($byRate as $rate => $sums) {
            $rows[] = ['rate' => (float) $rate, 'base' => round($sums['base'], 2), 'vat' => round($sums['vat'], 2)];
        }
        usort($rows, fn ($a, $b) => $a['rate'] <=> $b['rate']);

        return [round($gross, 2), round($vat, 2), $rows];
    }

    /**
     * Predictive analytics (Phase 3):
     * - revenue forecast: least-squares trend over the last 30 days of
     *   confirmed sales, projected 14 days ahead;
     * - stockout risk: average daily consumption (OUT movements, last 30
     *   days) per product → estimated days until stockout.
     */
    public function forecast(Request $request)
    {
        $days = 30;
        $horizon = 14;
        $from = now()->subDays($days - 1)->format('Y-m-d');

        // --- revenue trend ---
        $daily = Sale::where('status', Sale::STATUS_CONFIRMED)
            ->where('sale_date', '>=', $from)
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get(['sale_date', DB::raw('SUM(total_amount) as revenue')])
            ->map(fn ($r) => [
                'date' => $r->sale_date->format('Y-m-d'),
                'revenue' => (float) $r->revenue,
            ]);

        // Least squares y = a + b*x over day index (missing days count as 0).
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($days - 1 - $i)->format('Y-m-d');
            $series[] = (float) ($daily->firstWhere('date', $date)['revenue'] ?? 0);
        }
        $n = count($series);
        $sumX = $n * ($n - 1) / 2;
        $sumY = array_sum($series);
        $sumXY = 0;
        $sumX2 = 0;
        foreach ($series as $x => $y) {
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        $den = $n * $sumX2 - $sumX * $sumX;
        $b = $den != 0 ? ($n * $sumXY - $sumX * $sumY) / $den : 0;
        $a = ($sumY - $b * $sumX) / $n;

        $projection = [];
        for ($i = 0; $i < $horizon; $i++) {
            $x = $n + $i;
            $projection[] = [
                'date' => now()->addDays($i + 1)->format('Y-m-d'),
                'revenue' => round(max(0, $a + $b * $x), 2),
            ];
        }

        // --- stockout risk ---
        $consumption = DB::table('stock_movements')
            ->where('movement_type', 'out')
            ->where('created_at', '>=', $from)
            ->groupBy('product_id')
            ->pluck(DB::raw('SUM(quantity) as consumed'), 'product_id');

        $risk = Product::where('is_active', true)->get()
            ->map(function (Product $p) use ($consumption, $days) {
                $perDay = (float) ($consumption[$p->id] ?? 0) / $days;
                $daysLeft = $perDay > 0
                    ? (int) floor((float) $p->quantity_in_stock / $perDay)
                    : null;

                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'quantity_in_stock' => $p->quantity_in_stock,
                    'daily_consumption' => round($perDay, 3),
                    'days_until_stockout' => $daysLeft,
                ];
            })
            ->filter(fn ($r) => $r['days_until_stockout'] !== null)
            ->sortBy('days_until_stockout')
            ->values()
            ->take(10);

        return response()->json([
            'window_days' => $days,
            'horizon_days' => $horizon,
            'daily_revenue' => $daily->values()->all(),
            'trend_per_day' => round($b, 2),
            'projection' => $projection,
            'projected_total' => round(array_sum(array_column($projection, 'revenue')), 2),
            'stockout_risk' => $risk->all(),
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
