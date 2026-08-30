<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\HrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->employee = Employee::create([
            'code' => 'E-1', 'first_name' => 'Sami', 'last_name' => 'Trabelsi',
            'base_salary' => 1500, 'hire_date' => '2025-01-01',
        ]);
    }

    public function test_clock_in_then_out_computes_hours(): void
    {
        HrService::clockIn($this->employee, '2026-08-20', '08:00:00');
        $rec = HrService::clockOut($this->employee, '2026-08-20', '16:30:00');

        $this->assertEquals('8.50', $rec->hours);
    }

    public function test_cannot_clock_in_twice(): void
    {
        HrService::clockIn($this->employee, '2026-08-20', '08:00:00');
        $this->expectException(InvalidTransition::class);
        HrService::clockIn($this->employee, '2026-08-20', '09:00:00');
    }

    public function test_cannot_clock_out_without_clock_in(): void
    {
        $this->expectException(InvalidTransition::class);
        HrService::clockOut($this->employee, '2026-08-20', '16:00:00');
    }

    public function test_leave_request_counts_inclusive_days_and_balance_updates(): void
    {
        $req = HrService::requestLeave($this->employee, 'annual', '2026-08-10', '2026-08-14', 'family');
        $this->assertEquals('5.0', $req->days); // inclusive

        $before = HrService::leaveBalance($this->employee, 2026);
        $this->assertEquals(30, $before['allowance']);
        $this->assertEquals(0.0, $before['used']); // pending doesn't count

        HrService::decideLeave($req, true, $this->manager);
        $after = HrService::leaveBalance($this->employee, 2026);
        $this->assertEquals(5.0, $after['used']);
        $this->assertEquals(25.0, $after['remaining']);
    }

    public function test_cannot_decide_a_non_pending_leave(): void
    {
        $req = HrService::requestLeave($this->employee, 'sick', '2026-08-10', '2026-08-10', '');
        HrService::decideLeave($req, true, $this->manager);
        $this->expectException(InvalidTransition::class);
        HrService::decideLeave($req, false, $this->manager);
    }

    public function test_expense_claim_lifecycle(): void
    {
        $claim = HrService::submitClaim($this->employee, '2026-08-15', 'travel', 120.50, 'taxi');
        $this->assertEquals(ExpenseClaim::STATUS_PENDING, $claim->status);

        HrService::decideClaim($claim, true, $this->manager);
        $this->assertEquals(ExpenseClaim::STATUS_APPROVED, $claim->refresh()->status);

        HrService::reimburseClaim($claim);
        $this->assertEquals(ExpenseClaim::STATUS_REIMBURSED, $claim->refresh()->status);
    }

    public function test_cannot_reimburse_unapproved_claim(): void
    {
        $claim = HrService::submitClaim($this->employee, '2026-08-15', 'travel', 50, '');
        $this->expectException(InvalidTransition::class);
        HrService::reimburseClaim($claim);
    }

    public function test_http_flow_and_rbac(): void
    {
        // employee self-service create is allowed for any authenticated user
        $emp = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($emp, 'api')
            ->postJson('/api/v1/hr/leave', [
                'employee' => $this->employee->id, 'type' => 'annual',
                'start_date' => '2026-09-01', 'end_date' => '2026-09-03',
            ])->assertCreated()->assertJsonPath('days', '3.0');

        $req = LeaveRequest::latest('id')->first();
        // employees cannot approve
        $this->actingAs($emp, 'api')->postJson("/api/v1/hr/leave/{$req->id}/approve")->assertForbidden();
        // managers can
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/hr/leave/{$req->id}/approve")
            ->assertOk()->assertJsonPath('status', 'approved');
    }
}
