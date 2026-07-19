<?php

namespace Tests\Feature;

use App\Exceptions\UnbalancedEntry;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\DocumentService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        // sells for 10, costs 6
        $this->product = Product::create([
            'sku' => 'P-1', 'name' => 'P', 'sale_price' => 10, 'cost_price' => 6,
        ]);
    }

    // ---------- chart of accounts ----------

    public function test_default_chart_of_accounts_is_seeded(): void
    {
        $this->assertSame(8, Account::count());
        $this->assertSame('Sales revenue', Account::where('code', Account::REVENUE)->value('name'));
        $this->assertTrue(Account::where('code', Account::INVENTORY)->first()->isDebitNormal());
        $this->assertFalse(Account::where('code', Account::PAYABLE)->first()->isDebitNormal());
    }

    // ---------- double-entry enforcement ----------

    public function test_unbalanced_entry_is_rejected(): void
    {
        $this->expectException(UnbalancedEntry::class);
        AccountingService::post(
            lines: [
                ['account' => Account::CASH, 'debit' => 100],
                ['account' => Account::REVENUE, 'credit' => 90], // does not balance
            ],
            user: $this->manager,
        );
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_balanced_entry_is_posted_with_a_number(): void
    {
        $entry = AccountingService::post(
            lines: [
                ['account' => Account::CASH, 'debit' => 250, 'label' => 'Owner deposit'],
                ['account' => Account::EQUITY, 'credit' => 250, 'label' => 'Owner deposit'],
            ],
            user: $this->manager,
            memo: 'Opening capital',
        );

        $this->assertStringStartsWith('JE-', $entry->number);
        $this->assertCount(2, $entry->lines);
        $this->assertEqualsWithDelta(250.0, $entry->totalDebit(), 0.001);
    }

    // ---------- auto-posting ----------

    private function makeSale(string $qty = '4'): Sale
    {
        $customer = Customer::create(['name' => 'C']);
        $sale = Sale::create([
            'number' => DocumentService::nextNumber('SO', Sale::class),
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateString(),
            'created_by' => $this->manager->id,
        ]);
        SaleLine::create([
            'sale_id' => $sale->id, 'product_id' => $this->product->id,
            'quantity' => $qty, 'unit_price' => 10,
        ]);
        $sale->load('lines')->recomputeTotal();

        return $sale->refresh();
    }

    public function test_confirming_a_sale_posts_revenue_and_cogs(): void
    {
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'in',
            quantity: 10, user: $this->manager,
        );
        $sale = $this->makeSale('4'); // 4 × 10 = 40 revenue, 4 × 6 = 24 cogs

        DocumentService::confirmSale($sale, $this->manager);

        $entry = JournalEntry::with('lines.account')
            ->where('reference_type', 'sale')->where('reference_id', $sale->id)->firstOrFail();

        $byCode = $entry->lines->keyBy(fn ($l) => $l->account->code);
        $this->assertEqualsWithDelta(40.0, (float) $byCode[Account::RECEIVABLE]->debit, 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $byCode[Account::REVENUE]->credit, 0.001);
        $this->assertEqualsWithDelta(24.0, (float) $byCode[Account::COGS]->debit, 0.001);
        $this->assertEqualsWithDelta(24.0, (float) $byCode[Account::INVENTORY]->credit, 0.001);
        // the entry balances
        $this->assertEqualsWithDelta(
            $entry->lines->sum('debit'), $entry->lines->sum('credit'), 0.001
        );
    }

    public function test_receiving_a_purchase_posts_inventory_and_payable(): void
    {
        $supplier = Supplier::create(['name' => 'S']);
        $po = PurchaseOrder::create([
            'number' => DocumentService::nextNumber('PO', PurchaseOrder::class),
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'created_by' => $this->manager->id,
        ]);
        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'product_id' => $this->product->id,
            'quantity' => 10, 'unit_price' => 6, // total 60
        ]);
        $po->load('lines')->recomputeTotal();
        $po->refresh();

        DocumentService::confirmPurchase($po, $this->manager);
        DocumentService::receivePurchase($po, $this->manager);

        $entry = JournalEntry::with('lines.account')
            ->where('reference_type', 'purchase')->where('reference_id', $po->id)->firstOrFail();
        $byCode = $entry->lines->keyBy(fn ($l) => $l->account->code);

        $this->assertEqualsWithDelta(60.0, (float) $byCode[Account::INVENTORY]->debit, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $byCode[Account::PAYABLE]->credit, 0.001);
    }

    public function test_cancelling_a_confirmed_sale_posts_a_reversal(): void
    {
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'in',
            quantity: 10, user: $this->manager,
        );
        $sale = $this->makeSale('4');
        DocumentService::confirmSale($sale, $this->manager);
        DocumentService::cancelSale($sale, $this->manager);

        $entries = JournalEntry::with('lines')
            ->where('reference_type', 'sale')->where('reference_id', $sale->id)->get();

        // original + reversal, and together they net to zero
        $this->assertCount(2, $entries);
        $netDebit = $entries->flatMap->lines->sum('debit');
        $netCredit = $entries->flatMap->lines->sum('credit');
        $this->assertEqualsWithDelta($netDebit, $netCredit, 0.001);
        $this->assertEqualsWithDelta(128.0, $netDebit, 0.001); // (40+24) × 2
    }

    // ---------- statements ----------

    public function test_trial_balance_balances_and_income_statement_computes_profit(): void
    {
        StockService::recordMovement(
            productId: $this->product->id, movementType: 'in',
            quantity: 10, user: $this->manager,
        );
        $sale = $this->makeSale('4');
        DocumentService::confirmSale($sale, $this->manager);

        $tb = AccountingService::trialBalance();
        $this->assertEqualsWithDelta($tb['total_debit'], $tb['total_credit'], 0.001);

        $is = AccountingService::incomeStatement();
        $this->assertEqualsWithDelta(40.0, $is['total_income'], 0.001);
        $this->assertEqualsWithDelta(24.0, $is['total_expenses'], 0.001);
        $this->assertEqualsWithDelta(16.0, $is['net_profit'], 0.001);
    }

    // ---------- API + RBAC ----------

    public function test_employee_cannot_post_manual_entry_but_can_read_journal(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);

        $this->actingAs($employee, 'api')->getJson('/api/v1/accounting/entries')->assertOk();
        $this->actingAs($employee, 'api')
            ->postJson('/api/v1/accounting/entries', [
                'lines' => [
                    ['account' => Account::CASH, 'debit' => 10],
                    ['account' => Account::EQUITY, 'credit' => 10],
                ],
            ])->assertForbidden();

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/accounting/entries', [
                'lines' => [
                    ['account' => Account::CASH, 'debit' => 10],
                    ['account' => Account::EQUITY, 'credit' => 10],
                ],
            ])->assertCreated();
    }

    public function test_api_rejects_unbalanced_manual_entry(): void
    {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/accounting/entries', [
                'lines' => [
                    ['account' => Account::CASH, 'debit' => 10],
                    ['account' => Account::EQUITY, 'credit' => 7],
                ],
            ])->assertStatus(422);
    }
}
