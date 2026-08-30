<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use Illuminate\Support\Facades\DB;

/**
 * Report builder. Runs a small, safe query spec (source / group-by dimension /
 * measure) against the operational tables and returns labelled rows the UI can
 * table or chart. Only whitelisted dimensions and measures are accepted, so the
 * spec can come straight from the client.
 */
class BiService
{
    public const GROUPS = ['month', 'customer', 'product'];
    public const MEASURES = ['total', 'count'];

    /**
     * @return array<int,array{label:string,value:float}>
     */
    public static function run(string $source, string $groupBy, string $measure): array
    {
        if ($source !== 'sales') {
            throw new InvalidTransition("Unknown report source: {$source}.");
        }
        if (! in_array($groupBy, self::GROUPS, true)) {
            throw new InvalidTransition("Unknown grouping: {$groupBy}.");
        }
        if (! in_array($measure, self::MEASURES, true)) {
            throw new InvalidTransition("Unknown measure: {$measure}.");
        }

        return match ($groupBy) {
            'month' => self::byMonth($measure),
            'customer' => self::byCustomer($measure),
            'product' => self::byProduct($measure),
        };
    }

    private static function byMonth(string $measure): array
    {
        $value = $measure === 'total' ? 'coalesce(sum(total_amount),0)' : 'count(*)';
        // Month bucket differs by driver: Postgres in production, SQLite in tests.
        $month = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', sale_date)"
            : "to_char(sale_date, 'YYYY-MM')";
        $rows = DB::table('sales')
            ->where('status', '!=', 'cancelled')
            ->selectRaw("{$month} as label, {$value} as value")
            ->groupBy('label')->orderBy('label')->get();

        return self::shape($rows);
    }

    private static function byCustomer(string $measure): array
    {
        $value = $measure === 'total' ? 'coalesce(sum(sales.total_amount),0)' : 'count(*)';
        $rows = DB::table('sales')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.status', '!=', 'cancelled')
            ->selectRaw("customers.name as label, {$value} as value")
            ->groupBy('customers.name')->orderByDesc('value')->get();

        return self::shape($rows);
    }

    private static function byProduct(string $measure): array
    {
        $value = $measure === 'total'
            ? 'coalesce(sum(sale_lines.quantity * sale_lines.unit_price),0)'
            : 'coalesce(sum(sale_lines.quantity),0)';
        $rows = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->selectRaw("products.name as label, {$value} as value")
            ->groupBy('products.name')->orderByDesc('value')->get();

        return self::shape($rows);
    }

    private static function shape($rows): array
    {
        return $rows->map(fn ($r) => [
            'label' => (string) $r->label,
            'value' => round((float) $r->value, 2),
        ])->values()->all();
    }
}
