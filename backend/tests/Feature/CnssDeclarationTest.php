<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FeatureFlag;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The CNSS social-security declaration aggregated from payslips. */
class CnssDeclarationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    private function makeRun(string $period, string $status, array $slips): PayrollRun
    {
        $run = PayrollRun::create(['number' => 'PR-'.uniqid(), 'period_month' => $period, 'status' => $status,
            'created_by' => $this->manager->id]);
        foreach ($slips as $s) {
            $emp = Employee::create(['code' => $s['code'], 'first_name' => $s['name'], 'base_salary' => 1000]);
            Payslip::create(['payroll_run_id' => $run->id, 'employee_id' => $emp->id,
                'gross_pay' => $s['gross'], 'cnss_employee' => $s['ee'], 'cnss_employer' => $s['er'], 'net_pay' => $s['gross']]);
        }

        return $run;
    }

    public function test_declaration_aggregates_posted_payslips(): void
    {
        $this->makeRun('2026-03-01', PayrollRun::STATUS_APPROVED, [
            ['code' => 'E1', 'name' => 'Ali', 'gross' => 1000, 'ee' => 90.8, 'er' => 162.5],
            ['code' => 'E2', 'name' => 'Sam', 'gross' => 2000, 'ee' => 181.6, 'er' => 325.0],
        ]);

        $res = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/payroll/cnss-declaration?from=2026-01-01&to=2026-03-31')->assertOk();

        $this->assertSame(2, $res->json('employee_count'));
        $this->assertEqualsWithDelta(3000, $res->json('total_gross'), 0.001);
        $this->assertEqualsWithDelta(272.4, $res->json('total_employee_contribution'), 0.001);
        $this->assertEqualsWithDelta(487.5, $res->json('total_employer_contribution'), 0.001);
        $this->assertEqualsWithDelta(759.9, $res->json('total_contribution'), 0.001);
    }

    public function test_draft_runs_are_excluded(): void
    {
        $this->makeRun('2026-03-01', PayrollRun::STATUS_DRAFT, [
            ['code' => 'E1', 'name' => 'Ali', 'gross' => 1000, 'ee' => 90.8, 'er' => 162.5],
        ]);

        $res = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/payroll/cnss-declaration?from=2026-01-01&to=2026-03-31')->assertOk();
        $this->assertSame(0, $res->json('employee_count'));
    }

    public function test_the_payroll_module_gates_the_declaration(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'payroll'], ['enabled' => false]);
        FeatureFlag::flush();
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/payroll/cnss-declaration')->assertStatus(404);
    }

    public function test_an_ordinary_employee_cannot_read_it(): void
    {
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->getJson('/api/v1/payroll/cnss-declaration')->assertStatus(403);
    }
}
