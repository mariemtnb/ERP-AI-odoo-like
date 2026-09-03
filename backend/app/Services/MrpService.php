<?php

namespace App\Services;

use App\Models\BillOfMaterials;
use App\Models\Product;

/**
 * Basic MRP (material requirements planning). Explodes a product's bill of
 * materials level by level for a demand quantity, netting each item against the
 * stock already on hand: what remains must be procured. A component that has its
 * own BOM is a sub-assembly to manufacture (and is exploded further); one that
 * does not is bought.
 */
class MrpService
{
    private const MAX_DEPTH = 20;

    public static function explode(Product $product, float $qty): array
    {
        $lines = [];
        self::process($product, $qty, 0, [], $lines);

        // Roll the net requirements up per product, split by how they are sourced.
        $toBuy = [];
        $toMake = [];
        foreach ($lines as $l) {
            if ($l['level'] === 0 || $l['net'] <= 0) {
                continue;   // the top item is the demand itself, not a requirement
            }
            $into = $l['source'] === 'make' ? '_make' : '_buy';
            if ($into === '_make') {
                $toMake[$l['product_id']] ??= ['product_id' => $l['product_id'], 'name' => $l['name'], 'net' => 0.0];
                $toMake[$l['product_id']]['net'] += $l['net'];
            } else {
                $toBuy[$l['product_id']] ??= ['product_id' => $l['product_id'], 'name' => $l['name'], 'net' => 0.0];
                $toBuy[$l['product_id']]['net'] += $l['net'];
            }
        }
        $round = fn ($a) => array_map(function ($r) {
            $r['net'] = round($r['net'], 3);

            return $r;
        }, array_values($a));

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'lines' => $lines,                 // indented explosion, per node
            'to_manufacture' => $round($toMake),
            'to_purchase' => $round($toBuy),
        ];
    }

    /**
     * @param  array<int,bool>  $path products currently being expanded (cycle guard)
     * @param  array<int,array<string,mixed>>  $lines accumulator
     */
    private static function process(Product $product, float $required, int $level, array $path, array &$lines): void
    {
        $onHand = max(0.0, (float) $product->quantity_in_stock);
        $net = max(0.0, round($required - $onHand, 3));
        $bom = BillOfMaterials::where('product_id', $product->id)->where('is_active', true)
            ->with('components.component')->first();
        $cyclic = isset($path[$product->id]);
        $source = ($bom && ! $cyclic) ? 'make' : 'buy';

        $lines[] = [
            'level' => $level,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'required' => round($required, 3),
            'on_hand' => round($onHand, 3),
            'net' => $net,
            'source' => $source,
        ];

        if ($source !== 'make' || $net <= 0 || $level >= self::MAX_DEPTH) {
            return;
        }

        $output = max(0.000001, (float) $bom->output_quantity);
        $scale = $net / $output;   // only produce the shortfall
        $path[$product->id] = true;
        foreach ($bom->components as $component) {
            if (! $component->component) {
                continue;
            }
            self::process($component->component, (float) $component->quantity * $scale, $level + 1, $path, $lines);
        }
    }
}
