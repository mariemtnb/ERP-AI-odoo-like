<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Pricelist;
use App\Models\Product;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pricelists, quantity breaks, per-line discounts, and how they reach sales & POS. */
class PricingTest extends TestCase
{
    use RefreshDatabase;

    private function product(float $price = 100, ?int $categoryId = null): Product
    {
        return Product::create([
            'sku' => 'P'.uniqid(), 'name' => 'Widget', 'sale_price' => $price,
            'cost_price' => 0, 'category_id' => $categoryId, 'unit' => 'pcs',
        ]);
    }

    public function test_without_a_pricelist_the_base_price_stands(): void
    {
        $p = $this->product(100);
        $this->assertSame(100.0, PricingService::priceFor($p, 1, null));
    }

    public function test_a_fixed_rule_on_the_default_pricelist_applies(): void
    {
        $p = $this->product(100);
        $list = Pricelist::create(['name' => 'Retail', 'is_default' => true]);
        $list->rules()->create(['product_id' => $p->id, 'mode' => 'fixed', 'value' => 80]);

        $this->assertSame(80.0, PricingService::priceFor($p, 1, null));
    }

    public function test_a_percentage_discount_rule_applies(): void
    {
        $p = $this->product(200);
        $list = Pricelist::create(['name' => 'Sale', 'is_default' => true]);
        $list->rules()->create(['product_id' => $p->id, 'mode' => 'discount', 'value' => 25]);

        $this->assertSame(150.0, PricingService::priceFor($p, 1, null)); // 200 − 25%
    }

    public function test_a_quantity_break_beats_a_lower_threshold_rule(): void
    {
        $p = $this->product(100);
        $list = Pricelist::create(['name' => 'Wholesale', 'is_default' => true]);
        $list->rules()->create(['product_id' => $p->id, 'min_qty' => 1, 'mode' => 'fixed', 'value' => 95]);
        $list->rules()->create(['product_id' => $p->id, 'min_qty' => 10, 'mode' => 'fixed', 'value' => 80]);

        $this->assertSame(95.0, PricingService::priceFor($p, 5, null));
        $this->assertSame(80.0, PricingService::priceFor($p, 10, null));
    }

    public function test_a_product_rule_beats_a_category_rule(): void
    {
        $cat = Category::create(['name' => 'Tools']);
        $p = $this->product(100, $cat->id);
        $list = Pricelist::create(['name' => 'Mixed', 'is_default' => true]);
        $list->rules()->create(['category_id' => $cat->id, 'mode' => 'fixed', 'value' => 90]);
        $list->rules()->create(['product_id' => $p->id, 'mode' => 'fixed', 'value' => 70]);

        $this->assertSame(70.0, PricingService::priceFor($p, 1, null));
    }

    public function test_a_customers_own_pricelist_overrides_the_default(): void
    {
        $p = $this->product(100);
        $default = Pricelist::create(['name' => 'Default', 'is_default' => true]);
        $default->rules()->create(['product_id' => $p->id, 'mode' => 'fixed', 'value' => 90]);
        $vip = Pricelist::create(['name' => 'VIP']);
        $vip->rules()->create(['product_id' => $p->id, 'mode' => 'fixed', 'value' => 60]);

        $customer = Customer::create(['name' => 'Ahmed', 'pricelist_id' => $vip->id]);
        $this->assertSame(60.0, PricingService::priceFor($p, 1, $customer));
    }

    public function test_a_sale_resolves_the_price_and_applies_a_line_discount(): void
    {
        $user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $p = $this->product(100);
        $list = Pricelist::create(['name' => 'Retail', 'is_default' => true]);
        $list->rules()->create(['product_id' => $p->id, 'mode' => 'fixed', 'value' => 80]);
        $customer = Customer::create(['name' => 'Sonia']);

        $res = $this->actingAs($user, 'api')->postJson('/api/v1/sales', [
            'customer' => $customer->id,
            'sale_date' => '2026-01-10',
            // no unit_price → resolved to 80; then 10% off → 72/unit, ×2 = 144
            'lines' => [['product' => $p->id, 'quantity' => 2, 'discount_pct' => 10]],
        ])->assertCreated();

        $res->assertJsonPath('lines.0.unit_price', '80.00');
        $res->assertJsonPath('lines.0.discount_pct', '10.00');
        $res->assertJsonPath('lines.0.subtotal', '144.00');
        $res->assertJsonPath('total_amount', '144.00');
    }

    public function test_the_resolve_endpoint_returns_the_pricelist_price(): void
    {
        $user = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $p = $this->product(100);
        $list = Pricelist::create(['name' => 'Retail', 'is_default' => true]);
        $list->rules()->create(['product_id' => $p->id, 'mode' => 'discount', 'value' => 20]);

        $this->actingAs($user, 'api')
            ->getJson("/api/v1/pricing/resolve?product={$p->id}&quantity=1")
            ->assertOk()
            ->assertJsonPath('unit_price', '80.00')
            ->assertJsonPath('base_price', '100.00');
    }
}
