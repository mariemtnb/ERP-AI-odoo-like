<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Services\HrService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class HrController extends Controller
{
    // ---------- attendance ----------

    public function attendance(Request $request)
    {
        $query = AttendanceRecord::with('employee')->orderByDesc('work_date')->orderByDesc('id');
        if ($e = $request->query('employee')) {
            $query->where('employee_id', $e);
        }

        return response()->json(DrfPagination::paginate($query, $request, fn (AttendanceRecord $r) => $r->toApi()));
    }

    public function clockIn(Request $request)
    {
        $data = $request->validate([
            'employee' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'date_format:H:i,H:i:s'],
        ]);

        return $this->guard(fn () => HrService::clockIn(
            Employee::findOrFail($data['employee']), $data['date'] ?? null, $data['time'] ?? null
        )->toApi(), 201);
    }

    public function clockOut(Request $request)
    {
        $data = $request->validate([
            'employee' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'date_format:H:i,H:i:s'],
        ]);

        return $this->guard(fn () => HrService::clockOut(
            Employee::findOrFail($data['employee']), $data['date'] ?? null, $data['time'] ?? null
        )->toApi());
    }

    // ---------- leave ----------

    public function leaveIndex(Request $request)
    {
        $query = LeaveRequest::with(['employee', 'decider'])->orderByDesc('created_at')->orderByDesc('id');
        if ($e = $request->query('employee')) {
            $query->where('employee_id', $e);
        }
        if ($s = $request->query('status')) {
            $query->where('status', $s);
        }

        return response()->json(DrfPagination::paginate($query, $request, fn (LeaveRequest $r) => $r->toApi()));
    }

    public function requestLeave(Request $request)
    {
        $data = $request->validate([
            'employee' => ['required', 'integer', 'exists:employees,id'],
            'type' => ['required', 'string', 'in:annual,sick,unpaid'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->guard(fn () => HrService::requestLeave(
            Employee::findOrFail($data['employee']), $data['type'], $data['start_date'], $data['end_date'], $data['reason'] ?? ''
        )->toApi(), 201);
    }

    public function decideLeave(Request $request, LeaveRequest $leaveRequest, string $decision)
    {
        return $this->guard(fn () => HrService::decideLeave($leaveRequest, $decision === 'approve', $request->user())->toApi());
    }

    public function leaveBalance(Request $request)
    {
        $data = $request->validate([
            'employee' => ['required', 'integer', 'exists:employees,id'],
            'year' => ['nullable', 'integer'],
        ]);

        return response()->json(HrService::leaveBalance(
            Employee::findOrFail($data['employee']), $data['year'] ?? null
        ));
    }

    // ---------- expense claims ----------

    public function expenseIndex(Request $request)
    {
        $query = ExpenseClaim::with(['employee', 'decider'])->orderByDesc('created_at')->orderByDesc('id');
        if ($e = $request->query('employee')) {
            $query->where('employee_id', $e);
        }
        if ($s = $request->query('status')) {
            $query->where('status', $s);
        }

        return response()->json(DrfPagination::paginate($query, $request, fn (ExpenseClaim $c) => $c->toApi()));
    }

    public function submitClaim(Request $request)
    {
        $data = $request->validate([
            'employee' => ['required', 'integer', 'exists:employees,id'],
            'claim_date' => ['required', 'date'],
            'category' => ['nullable', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->guard(fn () => HrService::submitClaim(
            Employee::findOrFail($data['employee']), $data['claim_date'], $data['category'] ?? '', (float) $data['amount'], $data['description'] ?? ''
        )->toApi(), 201);
    }

    public function decideClaim(Request $request, ExpenseClaim $expenseClaim, string $decision)
    {
        return $this->guard(function () use ($expenseClaim, $decision, $request) {
            if ($decision === 'reimburse') {
                return HrService::reimburseClaim($expenseClaim)->toApi();
            }

            return HrService::decideClaim($expenseClaim, $decision === 'approve', $request->user())->toApi();
        });
    }

    private function guard(callable $fn, int $ok = 200)
    {
        try {
            return response()->json($fn(), $ok);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }
    }
}
