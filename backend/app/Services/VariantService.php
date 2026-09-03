<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\DB;

/**
 * Generates concrete product variants from a template and chosen attribute
 * values. Every combination across the attributes (the cartesian product)
 * becomes an ordinary product carrying its attribute values and pointing back
 * to the template — so variants flow through stock, sales and purchasing like
 * any other product. Existing variants of the template are not duplicated.
 */
class VariantService
{
    /**
     * @param  array<int,array<int>>  $valueGroups one group of value ids per
     *         attribute, e.g. [[sizeS, sizeM], [red, blue]] → 4 variants.
     * @return array<int,Product> the variants created (existing ones skipped)
     */
    public static function generate(Product $template, array $valueGroups): array
    {
        $groups = array_values(array_filter($valueGroups, fn ($g) => ! empty($g)));
        if (empty($groups)) {
            throw new InvalidTransition('Choose at least one attribute value to generate variants.');
        }

        // Resolve and validate all value ids up front.
        $ids = array_unique(array_merge(...$groups));
        $values = ProductAttributeValue::with('attribute')->whereIn('id', $ids)->get()->keyBy('id');
        if ($values->count() !== count($ids)) {
            throw new InvalidTransition('One or more attribute values do not exist.');
        }

        $combos = self::cartesian($groups);

        return DB::transaction(function () use ($template, $combos, $values) {
            // What already exists, keyed by a sorted signature of its value ids.
            $existing = [];
            foreach ($template->variants()->with('attributeValues')->get() as $variant) {
                $existing[self::signature($variant->attributeValues->pluck('id')->all())] = true;
            }

            $created = [];
            foreach ($combos as $combo) {
                $sig = self::signature($combo);
                if (isset($existing[$sig])) {
                    continue;
                }
                $labels = array_map(fn ($id) => $values[$id]->value, $combo);
                $suffix = implode('-', $labels);

                $variant = Product::create([
                    'sku' => $template->sku.'-'.strtoupper(str_replace(' ', '', $suffix)),
                    'name' => $template->name.' ('.implode(' / ', $labels).')',
                    'category_id' => $template->category_id,
                    'description' => $template->description,
                    'cost_price' => $template->cost_price,
                    'sale_price' => $template->sale_price,
                    'unit' => $template->unit,
                    'uom_id' => $template->uom_id,
                    'template_id' => $template->id,
                    'min_stock_level' => $template->min_stock_level,
                ]);
                $variant->attributeValues()->sync($combo);
                $existing[$sig] = true;
                $created[] = $variant->load('attributeValues.attribute');
            }

            return $created;
        });
    }

    /** @param array<int,array<int>> $groups @return array<int,array<int>> */
    private static function cartesian(array $groups): array
    {
        $result = [[]];
        foreach ($groups as $group) {
            $next = [];
            foreach ($result as $prefix) {
                foreach ($group as $value) {
                    $next[] = array_merge($prefix, [$value]);
                }
            }
            $result = $next;
        }

        return $result;
    }

    /** @param array<int> $ids */
    private static function signature(array $ids): string
    {
        sort($ids);

        return implode(',', $ids);
    }
}
