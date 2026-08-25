<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Exceptions\UnbalancedEntry;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Services\PayrollService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Employees, advances on salary, and monthly pay runs. */
class PayrollController extends Controller
{
    // ---------------- employees ----------------

    public function employees(Request $request)
    {
        $query = Employee::with('bankAccount')->orderBy('first_name');
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('first_name', 'ilike', "%{$search}%")
                ->orWhere('last_name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%"));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (Employee $e) => $e->toApi())
        );
    }

    public function showEmployee(Employee $employee)
    {
        return response()->json($employee->load('bankAccount')->toApi() + [
            'advances' => $employee->advances->map(fn (EmployeeAdvance $a) => $a->toApi())->values()->all(),
        ]);
    }

    public function storeEmployee(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'hire_date' => ['sometimes', 'nullable', 'date'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email'],
            'rib' => ['sometimes', 'nullable', 'string', 'max:30'],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);
        foreach (['last_name', 'job_title', 'department', 'phone', 'email', 'rib'] as $f) {
            $data[$f] = $data[$f] ?? '';
        }
        $data['code'] = \App\Services\DocumentService::nextNumber('EMP', Employee::class);

        return response()->json(Employee::create($data)->toApi(), 201);
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'hire_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email'],
            'rib' => ['sometimes', 'nullable', 'string', 'max:30'],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $employee->update($data);

        return response()->json($employee->refresh()->toApi());
    }

    // ---------------- advances ----------------

    public function advances(Request $request)
    {
        $query = EmployeeAdvance::with('employee')->orderByDesc('id');
        if ($employeeId = $request->query('employee')) {
            $query->where('employee_id', $employeeId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (EmployeeAdvance $a) => $a->toApi())
        );
    }

    public function requestAdvance(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'request_date' => ['sometimes', 'nullable', 'date'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'method' => ['sometimes', Rule::in(['cash', 'bank_transfer'])],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
        ]);

        try {
            $advance = PayrollService::requestAdvance(
                employee: Employee::findOrFail($data['employee_id']),
                amount: (float) $data['amount'],
                user: $request->user(),
                requestDate: $data['request_date'] ?? null,
                reason: $data['reason'] ?? '',
                method: $data['method'] ?? 'cash',
                bankAccountId: $data['bank_account_id'] ?? null,
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($advance->load('employee')->toApi(), 201);
    }

    public function payAdvance(Request $request, EmployeeAdvance $advance)
    {
        $data = $request->validate(['date' => ['sometimes', 'nullable', 'date']]);

        try {
            $advance = PayrollService::payAdvance($advance, $request->user(), $data['date'] ?? null);
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($advance->load('employee')->toApi());
    }

    public function cancelAdvance(EmployeeAdvance $advance)
    {
        try {
            $advance = PayrollService::cancelAdvance($advance);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($advance->toApi());
    }

    // ---------------- pay runs ----------------

    public function runs(Request $request)
    {
        $query = PayrollRun::withCount('payslips')->orderByDesc('period_month')->orderByDesc('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (PayrollRun $r) => $r->toApi())
        );
    }

    public function showRun(PayrollRun $run)
    {
        return response()->json(
            $run->load(['payslips.employee', 'payslips.lines'])->toApi(withPayslips: true)
        );
    }

    public function createRun(Request $request)
    {
        $data = $request->validate([
            'period_month' => ['required', 'date'],
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        try {
            $run = PayrollService::createRun($data['period_month'], $request->user(), $data['label'] ?? '');
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($run->load('payslips.employee')->toApi(withPayslips: true), 201);
    }

    public function addLine(Request $request, Payslip $payslip)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([PayslipLine::EARNING, PayslipLine::DEDUCTION])],
            'label' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'is_bonus' => ['sometimes', 'boolean'],
            'employee_advance_id' => ['sometimes', 'nullable', 'integer', 'exists:employee_advances,id'],
        ]);

        try {
            $payslip = PayrollService::addLine(
                $payslip->load('run'),
                $data['type'],
                $data['label'],
                (float) $data['amount'],
                (bool) ($data['is_bonus'] ?? false),
                $data['employee_advance_id'] ?? null,
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($payslip->load('lines')->toApi(withLines: true));
    }

    public function removeLine(PayslipLine $line)
    {
        try {
            $payslip = PayrollService::removeLine($line->load('payslip.run'));
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($payslip->load('lines')->toApi(withLines: true));
    }

    public function approveRun(Request $request, PayrollRun $run)
    {
        $data = $request->validate(['date' => ['sometimes', 'nullable', 'date']]);

        try {
            $run = PayrollService::approveRun($run, $request->user(), $data['date'] ?? null);
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($run->load('payslips.employee')->toApi(withPayslips: true));
    }

    public function payRun(Request $request, PayrollRun $run)
    {
        $data = $request->validate([
            'method' => ['sometimes', Rule::in(['cash', 'bank_transfer'])],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'date' => ['sometimes', 'nullable', 'date'],
        ]);

        try {
            $run = PayrollService::payRun(
                $run,
                $request->user(),
                $data['method'] ?? 'bank_transfer',
                $data['bank_account_id'] ?? null,
                $data['date'] ?? null,
            );
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($run->toApi());
    }
}
