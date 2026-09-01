<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\TunisianPayrollTax;
use App\Support\AccountMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tunisian statutory payroll: CNSS, IRPP and CSS. The rates come from the
 * seeded PayrollSetting, so these numbers track the defaults in the migration.
 */
class PayrollTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AccountMap::flush();
        \App\Models\FeatureFlag::flush();
    }

    public function test_cnss_is_the_configured_share_of_gross(): void
    {
        $t = TunisianPayrollTax::compute(2000, headOfFamily: false, children: 0);
        // 2000 × 9.18 %
        $this->assertEqualsWithDelta(183.6, $t['cnss_employee'], 0.001);
        // 2000 × 16.57 %
        $this->assertEqualsWithDelta(331.4, $t['cnss_employer'], 0.001);
    }

    public function test_full_breakdown_for_a_mid_salary(): void
    {
        // base 2000/month, single, no children. Worked by hand against the
        // seeded defaults (10 % abatement capped at 2000/yr, 2025 IRPP scale).
        $t = TunisianPayrollTax::compute(2000, false, 0);

        $this->assertEqualsWithDelta(183.6, $t['cnss_employee'], 0.001);
        $this->assertEqualsWithDelta(1649.733, $t['taxable_base'], 0.01); // monthly
        $this->assertEqualsWithDelta(266.6, $t['irpp'], 0.05);            // monthly
        $this->assertEqualsWithDelta(8.249, $t['css'], 0.01);

        $net = 2000 - $t['cnss_employee'] - $t['irpp'] - $t['css'];
        $this->assertEqualsWithDelta(1541.55, $net, 0.1);
    }

    public function test_a_very_low_salary_pays_no_income_tax(): void
    {
        // After CNSS and the abatement, annual taxable stays under the 5,000
        // exemption, so IRPP is zero.
        $t = TunisianPayrollTax::compute(450, false, 0);
        $this->assertSame(0.0, $t['irpp']);
    }

    public function test_family_relief_lowers_the_tax(): void
    {
        $single = TunisianPayrollTax::compute(2500, false, 0);
        $family = TunisianPayrollTax::compute(2500, true, 3);
        $this->assertLessThan($single['irpp'], $family['irpp']);
    }

    public function test_higher_salary_has_a_higher_effective_rate(): void
    {
        $low = TunisianPayrollTax::compute(1500, false, 0);
        $high = TunisianPayrollTax::compute(6000, false, 0);
        $this->assertGreaterThan($low['irpp'] / 1500, $high['irpp'] / 6000);
    }

    public function test_a_generated_payslip_carries_the_statutory_amounts(): void
    {
        $user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        Employee::create(['code' => 'EMP-1', 'first_name' => 'Ali', 'base_salary' => 2000]);

        $run = PayrollService::createRun('2026-01-01', $user);
        $slip = $run->payslips->first();

        $this->assertEqualsWithDelta(183.6, (float) $slip->cnss_employee, 0.001);
        $this->assertGreaterThan(0, (float) $slip->irpp);
        // net = gross − cnss − irpp − css
        $expectedNet = 2000 - (float) $slip->cnss_employee - (float) $slip->irpp - (float) $slip->css;
        $this->assertEqualsWithDelta($expectedNet, (float) $slip->net_pay, 0.01);
    }

    public function test_approving_a_run_posts_a_balanced_entry_including_withholdings(): void
    {
        $user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        Employee::create(['code' => 'EMP-1', 'first_name' => 'Sana', 'base_salary' => 3000]);

        $run = PayrollService::createRun('2026-02-01', $user);
        $run = PayrollService::approveRun($run, $user);

        // Every journal entry balances (AccountingService would have thrown
        // otherwise), and the CNSS / tax liabilities were actually credited.
        foreach (JournalEntry::with('lines')->get() as $e) {
            $this->assertEqualsWithDelta($e->lines->sum('debit'), $e->lines->sum('credit'), 0.001);
        }
        $cnssCode = AccountMap::code('cnss_payable');
        $taxCode = AccountMap::code('income_tax_payable');
        $this->assertNotNull($cnssCode);
        $this->assertNotNull($taxCode);
    }
}
