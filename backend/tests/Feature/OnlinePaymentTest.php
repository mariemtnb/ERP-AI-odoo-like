<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\OnlinePayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\OnlinePaymentService;
use App\Services\Payments\PaymentGateway;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A gateway double whose verify() result we control. */
class FakeGateway implements PaymentGateway
{
    public function __construct(private bool $ok) {}

    public function key(): string { return 'fake'; }

    public function initiate(OnlinePayment $payment): string { return 'https://pay.test/'.$payment->token; }

    public function verify(OnlinePayment $payment): bool { return $this->ok; }
}

/** Paying a shared sale online through the sandbox gateway. */
class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
    }

    private function sharedSale(float $total = 100): Sale
    {
        User::create(['id' => 1, 'email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $customer = Customer::create(['name' => 'Ahmed', 'email' => 'a@b.c']);
        $product = Product::create(['sku' => 'P1', 'name' => 'Chair', 'sale_price' => $total, 'cost_price' => 0, 'unit' => 'pcs']);
        $sale = Sale::create(['number' => 'SO-1', 'customer_id' => $customer->id, 'sale_date' => '2026-01-10', 'created_by' => 1]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => $total]);
        $sale->load('lines')->recomputeTotal();
        $sale->ensureToken();

        return $sale->refresh();
    }

    public function test_initiating_a_payment_returns_a_checkout_url(): void
    {
        $sale = $this->sharedSale();

        $res = $this->postJson("/api/v1/portal/sales/{$sale->portal_token}/pay")
            ->assertOk()
            ->assertJsonStructure(['checkout_url']);

        // Sandbox checkout points at our own pay page for the created attempt.
        $payment = OnlinePayment::first();
        $this->assertSame('pending', $payment->status);
        $this->assertStringContainsString("/portal/pay/{$payment->token}", $res->json('checkout_url'));
    }

    public function test_confirming_marks_it_paid_and_posts_a_balanced_entry(): void
    {
        $sale = $this->sharedSale(120);
        $this->postJson("/api/v1/portal/sales/{$sale->portal_token}/pay")->assertOk();
        $payment = OnlinePayment::first();

        $this->postJson("/api/v1/portal/pay/{$payment->token}/confirm")
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->journal_entry_id);

        // The bank was debited and receivable credited, and the entry balances.
        $entry = JournalEntry::with('lines')->find($payment->journal_entry_id);
        $this->assertEqualsWithDelta($entry->lines->sum('debit'), $entry->lines->sum('credit'), 0.001);
        $this->assertEqualsWithDelta(120, $entry->lines->sum('debit'), 0.001);

        // The public document now shows as paid.
        $this->getJson("/api/v1/portal/sales/{$sale->portal_token}")
            ->assertOk()->assertJsonPath('paid_online', true);
    }

    public function test_confirming_twice_does_not_double_post(): void
    {
        $sale = $this->sharedSale();
        $this->postJson("/api/v1/portal/sales/{$sale->portal_token}/pay");
        $payment = OnlinePayment::first();

        $this->postJson("/api/v1/portal/pay/{$payment->token}/confirm")->assertOk();
        $this->postJson("/api/v1/portal/pay/{$payment->token}/confirm")->assertOk();

        $this->assertSame(1, JournalEntry::where('reference_type', 'online_payment')->count());
    }

    public function test_a_paid_sale_cannot_be_paid_again(): void
    {
        $sale = $this->sharedSale();
        $this->postJson("/api/v1/portal/sales/{$sale->portal_token}/pay");
        $this->postJson('/api/v1/portal/pay/'.OnlinePayment::first()->token.'/confirm');

        $this->postJson("/api/v1/portal/sales/{$sale->portal_token}/pay")->assertStatus(409);
    }

    public function test_confirm_is_blocked_when_the_gateway_cannot_verify(): void
    {
        $sale = $this->sharedSale();
        [$payment] = OnlinePaymentService::initiate($sale);

        $this->expectException(\App\Exceptions\InvalidTransition::class);
        try {
            OnlinePaymentService::confirm($payment, gateway: new FakeGateway(false));
        } finally {
            // Nothing was settled or posted.
            $this->assertSame('pending', $payment->refresh()->status);
            $this->assertSame(0, JournalEntry::where('reference_type', 'online_payment')->count());
        }
    }

    public function test_confirm_proceeds_once_the_gateway_verifies(): void
    {
        $sale = $this->sharedSale();
        [$payment] = OnlinePaymentService::initiate($sale);

        OnlinePaymentService::confirm($payment, gateway: new FakeGateway(true));

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertSame(1, JournalEntry::where('reference_type', 'online_payment')->count());
    }
}
