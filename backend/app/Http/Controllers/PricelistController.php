<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Pricelist;
use App\Models\PricelistRule;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Pricelists, their rules, and live price resolution. */
class PricelistController extends Controller
{
    public function index()
    {
        return response()->json([
            'results' => Pricelist::withCount('rules')->orderBy('name')->get()
                ->map(fn (Pricelist $p) => $p->toApi())->values()->all(),
        ]);
    }

    public function show(Pricelist $pricelist)
    {
        return response()->json($pricelist->load('rules.product', 'rules.category')->toApi(withRules: true));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $pricelist = DB::transaction(function () use ($data) {
            $list = Pricelist::create($data);
            $this->syncDefault($list);

            return $list;
        });

        return response()->json($pricelist->toApi(), 201);
    }

    public function update(Request $request, Pricelist $pricelist)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($pricelist, $data) {
            $pricelist->update($data);
            $this->syncDefault($pricelist);
        });

        return response()->json($pricelist->refresh()->toApi());
    }

    public function destroy(Pricelist $pricelist)
    {
        $pricelist->delete();

        return response()->json(null, 204);
    }

    public function addRule(Request $request, Pricelist $pricelist)
    {
        $data = $request->validate([
            'product_id' => ['sometimes', 'nullable', 'integer', 'exists:products,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'min_qty' => ['sometimes', 'numeric', 'min:0'],
            'mode' => ['required', Rule::in([PricelistRule::FIXED, PricelistRule::DISCOUNT])],
            'value' => ['required', 'numeric', 'min:0'],
        ]);

        if ($data['mode'] === PricelistRule::DISCOUNT && $data['value'] > 100) {
            return response()->json(['value' => ['A discount cannot exceed 100%.']], 422);
        }

        $rule = $pricelist->rules()->create($data + ['min_qty' => $data['min_qty'] ?? 0]);

        return response()->json($rule->load('product', 'category')->toApi(), 201);
    }

    public function removeRule(PricelistRule $rule)
    {
        $rule->delete();

        return response()->json(null, 204);
    }

    /** Live price for a product / quantity / customer — used by the sale & POS forms. */
    public function resolve(Request $request)
    {
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'customer' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
        ]);

        $product = Product::findOrFail($data['product']);
        $customer = ! empty($data['customer']) ? Customer::find($data['customer']) : null;
        $price = PricingService::priceFor($product, (float) ($data['quantity'] ?? 1), $customer);

        return response()->json([
            'product' => $product->id,
            'base_price' => (string) $product->sale_price,
            'unit_price' => number_format($price, 2, '.', ''),
            'pricelist' => PricingService::pricelistFor($customer)?->name,
        ]);
    }

    /** Only one pricelist may be the default; flip the others off. */
    private function syncDefault(Pricelist $list): void
    {
        if ($list->is_default) {
            Pricelist::where('id', '!=', $list->id)->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }
}
