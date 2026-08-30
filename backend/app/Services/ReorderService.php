<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReorderRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reordering rules — turns the low-stock signal into concrete replenishment.
 * A rule fires when a product's on-hand quantity is at or below its reorder
 * point; firing rules can be rolled up into draft purchase orders grouped by
 * supplier, ready for a buyer to review and confirm.
 */
class ReorderService
{
    /**
     * Products at or below their reorder point.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function suggestions(): array
    {
        $rules = ReorderRule::with(['product', 'supplier'])
            ->where('is_active', true)
            ->get();

        $out = [];
        foreach ($rules as $rule) {
            if (! $rule->product) {
                continue;
            }
            $current = (float) $rule->product->quantity_in_stock;
            if ($current > (float) $rule->min_qty) {
                continue;
            }
            $out[] = [
                'product' => $rule->product_id,
                'product_name' => $rule->product->name,
                'sku' => $rule->product->sku,
                'current_stock' => $current,
                'min_qty' => (float) $rule->min_qty,
                'reorder_qty' => (float) $rule->reorder_qty,
                'supplier' => $rule->supplier_id,
                'supplier_name' => $rule->supplier?->name,
            ];
        }

        return $out;
    }

    /**
     * Create one draft purchase order per supplier for every firing rule that
     * has a preferred supplier. Firing rules without a supplier are returned
     * separately so a buyer can assign one.
     *
     * @return array{orders:array<int,PurchaseOrder>, unassigned:array<int,array<string,mixed>>}
     */
    public static function generateDraftPurchaseOrders(User $user): array
    {
        $suggestions = self::suggestions();

        $bySupplier = [];
        $unassigned = [];
        foreach ($suggestions as $s) {
            if ($s['reorder_qty'] <= 0) {
                continue;
            }
            if ($s['supplier'] === null) {
                $unassigned[] = $s;

                continue;
            }
            $bySupplier[$s['supplier']][] = $s;
        }

        $orders = DB::transaction(function () use ($bySupplier, $user) {
            $created = [];
            foreach ($bySupplier as $supplierId => $items) {
                $po = PurchaseOrder::create([
                    'number' => DocumentService::nextNumber('PO', PurchaseOrder::class),
                    'supplier_id' => $supplierId,
                    'order_date' => now()->toDateString(),
                    'created_by' => $user->id,
                ]);
                foreach ($items as $item) {
                    $product = \App\Models\Product::find($item['product']);
                    PurchaseOrderLine::create([
                        'purchase_order_id' => $po->id,
                        'product_id' => $item['product'],
                        'quantity' => $item['reorder_qty'],
                        'unit_price' => (float) ($product->cost_price ?? 0),
                    ]);
                }
                $po->load('lines')->recomputeTotal();
                $created[] = $po->refresh();
            }

            return $created;
        });

        return ['orders' => $orders, 'unassigned' => $unassigned];
    }
}
