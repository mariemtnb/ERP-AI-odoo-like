<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBill;
use App\Services\AccountingService;
use App\Services\DocumentService;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Balance sheet, general ledger and aged AR/AP — read straight from the books. */
class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        $this->manager = User::create(['id' => 1, 'email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    private function entry(string $dr, string $cr, float $amount, string $date): void
    {
        AccountingService::post(lines: [
            ['account' => $dr, 'debit' => $amount],
            ['account' => $cr, 'credit' => $amount],
        ], user: $this->manager, memo: 'test', date: $date);
    }

    public function test_balance_sheet_balances_and_folds_the_result_into_equity(): void
    {
        $this->entry(Account::CASH, Account::EQUITY, 1000, '2026-01-01');   // capital in
        $this->entry(Account::CASH, Account::REVENUE, 500, '2026-02-01');   // a cash sale
        $this->entry('6000', Account::CASH, 200, '2026-02-15');            // an expense

        $res = $this->actingAs($this->manager, 'api')->getJson('/api/v1/reports/balance-sheet?as_of=2026-03-01')
            ->assertOk()->assertJsonPath('balanced', true);

        $this->assertEqualsWithDelta(1300, $res->json('total_assets'), 0.01);        // cash 1000+500-200
        $this->assertEqualsWithDelta(300, $res->json('result_for_period'), 0.01);    // 500 - 200
        $this->assertEqualsWithDelta(1300, $res->json('total_equity'), 0.01);        // equity 1000 + result 300
        $this->assertEqualsWithDelta($res->json('total_assets'), $res->json('total_liabilities_and_equity'), 0.01);
    }

    public function test_general_ledger_tracks_a_running_balance_with_opening(): void
    {
        $this->entry(Account::CASH, Account::EQUITY, 1000, '2026-01-01');
        $this->entry(Account::CASH, Account::REVENUE, 500, '2026-02-01');
        $this->entry('6000', Account::CASH, 200, '2026-02-15');

        $res = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/reports/general-ledger?account_code=1000&from=2026-02-01')
            ->assertOk();

        $this->assertEqualsWithDelta(1000, $res->json('opening_balance'), 0.01); // the January capital, before the range
        $this->assertEqualsWithDelta(1300, $res->json('closing_balance'), 0.01);

        $rows = $res->json('rows');
        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(1500, $rows[0]['balance'], 0.01); // after +500
        $this->assertEqualsWithDelta(1300, $rows[1]['balance'], 0.01); // after -200
    }

    public function test_aged_receivables_bucket_open_invoices(): void
    {
        $customer = Customer::create(['name' => 'Ahmed', 'email' => 'a@b.c']);
        $product = Product::create(['sku' => 'P1', 'name' => 'Chair', 'sale_price' => 1000, 'cost_price' => 0, 'unit' => 'pcs']);
        $sale = Sale::create(['number' => 'SO-1', 'customer_id' => $customer->id, 'sale_date' => '2026-01-01',
            'status' => Sale::STATUS_CONFIRMED, 'created_by' => 1]);
        SaleLine::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]);
        $sale->load('lines')->recomputeTotal();
        DocumentService::generateInvoice($sale->refresh())->update(['issued_at' => '2026-01-01 09:00:00']);

        // A partial payment leaves 600 outstanding.
        Payment::create(['number' => 'PAY-1', 'direction' => Payment::DIRECTION_IN, 'method' => Payment::METHOD_CASH,
            'amount' => 400, 'payment_date' => '2026-02-01', 'reference_type' => 'sale', 'reference_id' => $sale->id, 'created_by' => 1]);

        // Due 2026-01-31 (30d terms); at 2026-03-15 that is 43 days overdue → 31–60 bucket.
        $res = $this->actingAs($this->manager, 'api')->getJson('/api/v1/reports/aged-receivables?as_of=2026-03-15')->assertOk();

        $row = collect($res->json('rows'))->firstWhere('partner_id', $customer->id);
        $this->assertEqualsWithDelta(600, $row['total'], 0.01);
        $this->assertEqualsWithDelta(600, $row['d31_60'], 0.01);
        $this->assertEqualsWithDelta(0, $row['not_due'], 0.01);
        $this->assertEqualsWithDelta(600, $res->json('totals.total'), 0.01);
    }

    public function test_aged_payables_bucket_unpaid_bills(): void
    {
        $supplier = Supplier::create(['name' => 'Acme']);
        VendorBill::create(['number' => 'VB-1', 'supplier_id' => $supplier->id, 'bill_date' => '2026-03-01',
            'total_amount' => 500, 'status' => VendorBill::STATUS_MATCHED, 'created_by' => 1]);

        // Due 2026-03-31; at 2026-03-15 it is not yet due.
        $res = $this->actingAs($this->manager, 'api')->getJson('/api/v1/reports/aged-payables?as_of=2026-03-15')->assertOk();

        $row = collect($res->json('rows'))->firstWhere('partner_id', $supplier->id);
        $this->assertEqualsWithDelta(500, $row['not_due'], 0.01);
        $this->assertEqualsWithDelta(500, $row['total'], 0.01);
    }

    public function test_reports_are_manager_only(): void
    {
        $employee = User::create(['id' => 2, 'email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/reports/balance-sheet')->assertStatus(403);
    }
}
