<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Services\VariantService;
use Illuminate\Http\Request;

/** Product attributes and variant generation. */
class VariantController extends Controller
{
    /** Attributes and their allowed values. */
    public function attributes()
    {
        return response()->json(
            ProductAttribute::with('values')->orderBy('name')->get()->map(fn ($a) => $a->toApi())->all()
        );
    }

    public function storeAttribute(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', 'unique:product_attributes,name']]);

        return response()->json(ProductAttribute::create($data)->load('values')->toApi(), 201);
    }

    public function storeValue(Request $request, ProductAttribute $attribute)
    {
        $data = $request->validate(['value' => ['required', 'string', 'max:60']]);
        $value = $attribute->values()->firstOrCreate(['value' => $data['value']]);

        return response()->json(['id' => $value->id, 'attribute_id' => $attribute->id, 'value' => $value->value], 201);
    }

    /** Generate variants of a product from chosen attribute-value groups. */
    public function generate(Request $request, Product $product)
    {
        $data = $request->validate([
            'value_groups' => ['required', 'array', 'min:1'],
            'value_groups.*' => ['array', 'min:1'],
            'value_groups.*.*' => ['integer', 'exists:product_attribute_values,id'],
        ]);

        try {
            $variants = VariantService::generate($product, $data['value_groups']);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json([
            'created' => count($variants),
            'variants' => array_map(fn ($v) => $v->toApi(), $variants),
        ], 201);
    }
}
