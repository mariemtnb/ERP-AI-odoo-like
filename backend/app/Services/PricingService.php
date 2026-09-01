<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Pricelist;
use App\Models\PricelistRule;
use App\Models\Product;

/**
 * Works out the unit price for a product, honouring the applicable pricelist.
 *
 * The pricelist is the customer's own, or the default one, or none — in which
 * case the product's base sale price stands. Among the rules that match the
 * product and quantity, the most specific wins (product over category over
 * blanket), and within the same specificity the highest satisfied minimum
 * quantity wins, so a "10+ units" rule beats a "1+ units" rule.
 */
class PricingService
{
    /** The unit price for `$product` at `$qty`, for `$customer` if given. */
    public static function priceFor(Product $product, float $qty = 1, ?Customer $customer = null): float
    {
        $base = (float) $product->sale_price;

        $pricelist = self::pricelistFor($customer);
        if (! $pricelist) {
            return $base;
        }

        $rule = self::bestRule($pricelist, $product, $qty);

        return $rule ? $rule->priceFor($base) : $base;
    }

    /** Resolve which pricelist applies: the customer's, else the default. */
    public static function pricelistFor(?Customer $customer): ?Pricelist
    {
        if ($customer && $customer->pricelist_id) {
            $list = Pricelist::find($customer->pricelist_id);
            if ($list && $list->is_active) {
                return $list;
            }
        }

        return Pricelist::default();
    }

    private static function bestRule(Pricelist $pricelist, Product $product, float $qty): ?PricelistRule
    {
        $rules = $pricelist->rules()
            ->where('min_qty', '<=', $qty)
            ->where(fn ($q) => $q
                ->where('product_id', $product->id)
                ->orWhere('category_id', $product->category_id)
                ->orWhere(fn ($b) => $b->whereNull('product_id')->whereNull('category_id')))
            ->get()
            // A category rule that names a *different* category must not apply.
            ->filter(fn (PricelistRule $r) => $r->product_id === null
                || $r->product_id === $product->id)
            ->filter(fn (PricelistRule $r) => $r->category_id === null
                || $r->category_id === $product->category_id);

        return $rules
            ->sortByDesc(fn (PricelistRule $r) => [$r->specificity(), (float) $r->min_qty])
            ->first();
    }
}
