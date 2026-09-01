<?php

namespace Tests\Feature;

use App\Mail\SaleDocumentMail;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Emailing a sale to the customer and the public portal view. */
class PortalEmailTest extends TestCase
{
    use RefreshDatabase;

    private function sale(?string $email = 'buyer@example.com'): Sale
    {
        $customer = Customer::create(['name' => 'Ahmed', 'email' => $email ?? '']);
        $product = Product::create(['sku' => 'P1', 'name' => 'Chair', 'sale_price' => 50, 'cost_price' => 0, 'unit' => 'pcs']);
        $sale = Sale::create([
            'number' => 'SO-1', 'customer_id' => $customer->id, 'sale_date' => '2026-01-10', 'created_by' => 1,
        ]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 50]);
        $sale->load('lines')->recomputeTotal();

        return $sale->refresh();
    }

    public function test_emailing_a_sale_sends_it_and_records_the_token(): void
    {
        Mail::fake();
        $user = User::create(['id' => 1, 'email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $sale = $this->sale();

        $res = $this->actingAs($user, 'api')->postJson("/api/v1/sales/{$sale->id}/email")
            ->assertOk()
            ->assertJsonPath('sent', true)
            ->assertJsonPath('emailed_to', 'buyer@example.com');

        Mail::assertSent(SaleDocumentMail::class);
        $sale->refresh();
        $this->assertNotNull($sale->portal_token);
        $this->assertNotNull($sale->emailed_at);
        $this->assertStringContainsString("/portal/sales/{$sale->portal_token}", $res->json('portal_url'));
    }

    public function test_emailing_without_a_customer_email_is_rejected(): void
    {
        Mail::fake();
        $user = User::create(['id' => 1, 'email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $sale = $this->sale(email: null);

        $this->actingAs($user, 'api')->postJson("/api/v1/sales/{$sale->id}/email")->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_the_public_portal_returns_the_sale_for_a_valid_token(): void
    {
        User::create(['id' => 1, 'email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $sale = $this->sale();
        $token = $sale->ensureToken();

        // No authentication — this is the public customer view.
        $this->getJson("/api/v1/portal/sales/{$token}")
            ->assertOk()
            ->assertJsonPath('number', 'SO-1')
            ->assertJsonPath('customer.name', 'Ahmed')
            ->assertJsonPath('total_amount', '100.00')
            ->assertJsonCount(1, 'lines');
    }

    public function test_an_invalid_portal_token_is_not_found(): void
    {
        $this->getJson('/api/v1/portal/sales/nope-not-a-real-token')->assertStatus(404);
    }
}
