<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('sku', 'ilike', "%{$search}%")
                ->orWhere('name', 'ilike', "%{$search}%"));
        }
        if ($category = $request->query('category')) {
            $query->where('category_id', $category);
        }
        if (! is_null($request->query('is_active'))) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL));
        }
        if ($request->query('low_stock') === 'true') {
            $query->whereColumn('quantity_in_stock', '<=', 'min_stock_level');
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (Product $p) => $p->toApi())
        );
    }

    private function rules(?Product $product = null): array
    {
        return [
            'sku' => ['sometimes', 'required', 'string', 'max:50',
                Rule::unique('products', 'sku')->ignore($product?->id)],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'category' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'description' => ['sometimes', 'string'],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'uom_id' => ['sometimes', 'nullable', 'integer', 'exists:units_of_measure,id'],
            'min_stock_level' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private static function payload(array $data): array
    {
        if (array_key_exists('category', $data)) {
            $data['category_id'] = $data['category'];
            unset($data['category']);
        }

        return $data;
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        foreach (['sku', 'name'] as $required) {
            if (! isset($data[$required])) {
                return response()->json([$required => ['This field is required.']], 400);
            }
        }
        $product = Product::create(self::payload($data))->refresh();

        return response()->json($product->load('category')->toApi(), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load('category')->toApi());
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate($this->rules($product));
        $product->update(self::payload($data));

        return response()->json($product->load('category')->toApi());
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]); // soft delete

        return response()->json(null, 204);
    }
}
