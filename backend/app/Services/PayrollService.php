<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\Journal;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\User;
use App\Services\TunisianPayrollTax;
use App\Support\AccountMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payroll ("gestion de paie"), advances and bonuses.
 *
 * The money maths in one place, and every step that moves money posts a
 * balanced accounting entry through the configurable mapping — the same
 * discipline as sales, purchases and treasury. No tax or social-charge rate is
 * hardcoded: those are ordinary deduction lines whose amounts the company sets.
 */
class PayrollService
{
    // ---------------- advances ----------------

    /**
     * Register an advance request. Pending until approved — nothing is posted
     * and no money moves yet.
     */
    public static function requestAdvance(
        Employee $employee,
        float $amount,
        User $user,
        ?string $requestDate = null,
        string $reason = '',
        string $method = 'cash',
        ?int $bankAccountId = null,
    ): EmployeeAdvance {
        if ($amount <= 0) {
            throw new InvalidTransition('The advance amount must be greater than zero.');
        }

        return EmployeeAdvance::create([
            'number' => DocumentService::nextNumber('ADV', EmployeeAdvance::class),
            'employee_id' => $employee->id,
            'amount' => round($amount, 3),
            'request_date' => $requestDate ?? now()->toDateString(),
            'reason' => $reason,
            'method' => $method,
            'bank_account_id' => $bankAccountId,
            'status' => EmployeeAdvance::STATUS_PENDING,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Approve and pay an advance: money goes out now, and the amount sits as
     * "owed by the employee" until it is taken back from a payslip.
     *
     *   Dr Employee advances (asset)  /  Cr Cash or Bank
     */
    public static function payAdvance(EmployeeAdvance $advance, User $user, ?string $date = null): EmployeeAdvance
    {
        if ($advance->status !== EmployeeAdvance::STATUS_PENDING) {
            throw new InvalidTransition("Only a pending advance can be paid (status: {$advance->status}).");
        }

        return DB::transaction(function () use ($advance, $user, $date) {
            $treasury = $advance->method === 'bank_transfer'
                ? (BankAccount::find($advance->bank_account_id)?->glAccount?->code ?? AccountMap::code('bank'))
                : AccountMap::code('cash');

            $entry = AccountingService::post(
                lines: [
                    ['account' => AccountMap::code('employee_advances'), 'debit' => $advance->amount, 'label' => "Advance {$advance->number}"],
                    ['account' => $treasury, 'credit' => $advance->amount, 'label' => "Advance {$advance->number}"],
                ],
                user: $user,
                memo: "Advance on salary to {$advance->employee?->fullName()}" . ($advance->reason ? " — {$advance->reason}" : ''),
                referenceType: 'employee_advance',
                referenceId: $advance->id,
                date: $date,
                journalCode: Journal::PAYROLL,
            );

            $advance->update([
                'status' => EmployeeAdvance::STATUS_PAID,
                'paid_at' => $date ?? now()->toDateString(),
                'approved_by' => $user->id,
                'journal_entry_id' => $entry?->id,
            ]);

            return $advance->refresh();
        });
    }

    public static function cancelAdvance(EmployeeAdvance $advance): EmployeeAdvance
    {
        if ($advance->status !== EmployeeAdvance::STATUS_PENDING) {
            throw new InvalidTransition('Only a pending advance can be cancelled.');
        }
        $advance->update(['status' => EmployeeAdvance::STATUS_CANCELLED]);

        return $advance->refresh();
    }

    // ---------------- pay runs ----------------

    /**
     * Open a draft run for a month and generate one payslip per active
     * employee, pre-filled with their base salary and an automatic line to
     * start recovering any outstanding advance.
     */
    public static function createRun(string $periodMonth, User $user, string $label = ''): PayrollRun
    {
        $month = Carbon::parse($periodMonth)->startOfMonth();

        return DB::transaction(function () use ($month, $user, $label) {
            if (PayrollRun::whereDate('period_month', $month->toDateString())
                ->where('status', '!=', PayrollRun::STATUS_PAID)->exists()) {
                throw new InvalidTransition('An open payroll run already exists for this month.');
            }

            $run = PayrollRun::create([
                'number' => DocumentService::nextNumber('PR', PayrollRun::class),
                'period_month' => $month->toDateString(),
                'label' => $label ?: $month->translatedFormat('F Y'),
                'created_by' => $user->id,
            ]);

            foreach (Employee::where('is_active', true)->get() as $employee) {
                $slip = Payslip::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'base_salary' => $employee->base_salary,
                ]);

                // Start recovering outstanding advances, but never more than the
                // base salary — the payslip must not go negative on its own.
                $outstanding = $employee->outstandingAdvance();
                if ($outstanding > 0) {
                    $recover = min($outstanding, (float) $employee->base_salary);
                    $advance = $employee->advances()
                        ->where('status', EmployeeAdvance::STATUS_PAID)
                        ->orderBy('id')->first();
                    if ($advance && $recover > 0) {
                        PayslipLine::create([
                            'payslip_id' => $slip->id,
                            'type' => PayslipLine::DEDUCTION,
                            'label' => "Advance recovery {$advance->number}",
                            'amount' => min($recover, $advance->remaining()),
                            'employee_advance_id' => $advance->id,
                        ]);
                    }
                }

                self::recomputePayslip($slip);
            }

            return self::recomputeRun($run);
        });
    }

    /** Add a bonus (prime) or a deduction line to a payslip in a draft run. */
    public static function addLine(
        Payslip $payslip,
        string $type,
        string $label,
        float $amount,
        bool $isBonus = false,
        ?int $advanceId = null,
    ): Payslip {
        if ($payslip->run->status !== PayrollRun::STATUS_DRAFT) {
            throw new InvalidTransition('Lines can only be changed while the run is a draft.');
        }
        if (! in_array($type, [PayslipLine::EARNING, PayslipLine::DEDUCTION], true)) {
            throw new InvalidTransition('A line is either an earning or a deduction.');
        }
        if ($amount <= 0) {
            throw new InvalidTransition('The line amount must be greater than zero.');
        }

        return DB::transaction(function () use ($payslip, $type, $label, $amount, $isBonus, $advanceId) {
            PayslipLine::create([
                'payslip_id' => $payslip->id,
                'type' => $type,
                'label' => $label,
                'amount' => round($amount, 3),
                'is_bonus' => $isBonus,
                'employee_advance_id' => $advanceId,
            ]);
            self::recomputePayslip($payslip);
            self::recomputeRun($payslip->run);

            return $payslip->refresh();
        });
    }

    public static function removeLine(PayslipLine $line): Payslip
    {
        $payslip = $line->payslip;
        if ($payslip->run->status !== PayrollRun::STATUS_DRAFT) {
            throw new InvalidTransition('Lines can only be changed while the run is a draft.');
        }

        return DB::transaction(function () use ($line, $payslip) {
            $line->delete();
            self::recomputePayslip($payslip);
            self::recomputeRun($payslip->run);

            return $payslip->refresh();
        });
    }

    /**
     * Approve a run: post the whole payroll to the ledger in one entry.
     *
     *   Dr Salary expense       = Σ gross (base + bonuses)
     *   Cr Salaries payable     = Σ net
     *   Cr Employee advances    = Σ advance recovered   (relieves the asset)
     *   Cr Payroll deductions   = Σ other deductions
     */
    public static function approveRun(PayrollRun $run, User $user, ?string $date = null): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_DRAFT) {
            throw new InvalidTransition("Only a draft run can be approved (status: {$run->status}).");
        }
        $run->load('payslips.lines');
        if ($run->payslips->isEmpty()) {
            throw new InvalidTransition('This run has no payslips.');
        }

        return DB::transaction(function () use ($run, $user, $date) {
            $gross = round((float) $run->payslips->sum('gross_pay'), 3);
            $net = round((float) $run->payslips->sum('net_pay'), 3);
            $advance = round((float) $run->payslips->sum('advance_recovered'), 3);
            $deductions = round((float) $run->payslips->sum('deductions_total'), 3);
            $cnss = round((float) $run->payslips->sum('cnss_employee'), 3);
            $tax = round((float) $run->payslips->sum('irpp') + (float) $run->payslips->sum('css'), 3);

            // Gross (debit) is split across everything credited: net to the
            // employees, recovered advances, other deductions, and the CNSS /
            // IRPP+CSS withholdings now owed to the collecting bodies.
            $lines = [
                ['account' => AccountMap::code('salary_expense'), 'debit' => $gross, 'label' => "Payroll {$run->number}"],
                ['account' => AccountMap::code('salaries_payable'), 'credit' => $net, 'label' => "Net pay {$run->number}"],
            ];
            if ($advance > 0) {
                $lines[] = ['account' => AccountMap::code('employee_advances'), 'credit' => $advance, 'label' => "Advances recovered {$run->number}"];
            }
            if ($deductions > 0) {
                $lines[] = ['account' => AccountMap::code('payroll_deductions'), 'credit' => $deductions, 'label' => "Deductions {$run->number}"];
            }
            if ($cnss > 0) {
                $lines[] = ['account' => AccountMap::code('cnss_payable'), 'credit' => $cnss, 'label' => "CNSS withheld {$run->number}"];
            }
            if ($tax > 0) {
                $lines[] = ['account' => AccountMap::code('income_tax_payable'), 'credit' => $tax, 'label' => "IRPP & CSS withheld {$run->number}"];
            }

            $entry = AccountingService::post(
                lines: $lines,
                user: $user,
                memo: "Payroll {$run->label}",
                referenceType: 'payroll_run',
                referenceId: $run->id,
                date: $date ?? $run->period_month->endOfMonth()->toDateString(),
                journalCode: Journal::PAYROLL,
            );

            // Relieve the recovered advances against their source records.
            foreach ($run->payslips as $slip) {
                foreach ($slip->lines->whereNotNull('employee_advance_id') as $line) {
                    $adv = EmployeeAdvance::find($line->employee_advance_id);
                    if (! $adv) {
                        continue;
                    }
                    $recovered = round((float) $adv->recovered_amount + (float) $line->amount, 3);
                    $adv->update([
                        'recovered_amount' => $recovered,
                        'status' => $recovered >= (float) $adv->amount - 0.0005
                            ? EmployeeAdvance::STATUS_RECOVERED
                            : $adv->status,
                    ]);
                }
                $slip->update(['status' => 'approved']);
            }

            $run->update([
                'status' => PayrollRun::STATUS_APPROVED,
                'gross_total' => $gross,
                'net_total' => $net,
                'journal_entry_id' => $entry?->id,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            return $run->refresh();
        });
    }

    /**
     * Pay an approved run: the net leaves the treasury.
     *   Dr Salaries payable  /  Cr Bank or Cash
     */
    public static function payRun(
        PayrollRun $run,
        User $user,
        string $method = 'bank_transfer',
        ?int $bankAccountId = null,
        ?string $date = null,
    ): PayrollRun {
        if ($run->status !== PayrollRun::STATUS_APPROVED) {
            throw new InvalidTransition("Only an approved run can be paid (status: {$run->status}).");
        }

        return DB::transaction(function () use ($run, $user, $method, $bankAccountId, $date) {
            $treasury = $method === 'cash'
                ? AccountMap::code('cash')
                : (BankAccount::find($bankAccountId)?->glAccount?->code ?? AccountMap::code('bank'));

            AccountingService::post(
                lines: [
                    ['account' => AccountMap::code('salaries_payable'), 'debit' => $run->net_total, 'label' => "Salaries paid {$run->number}"],
                    ['account' => $treasury, 'credit' => $run->net_total, 'label' => "Salaries paid {$run->number}"],
                ],
                user: $user,
                memo: "Salaries paid — {$run->label}",
                referenceType: 'payroll_run',
                referenceId: $run->id,
                date: $date,
                journalCode: Journal::PAYROLL,
            );

            $run->payslips()->update(['status' => 'paid']);
            $run->update(['status' => PayrollRun::STATUS_PAID, 'paid_at' => now()]);

            return $run->refresh();
        });
    }

    // ---------------- recompute helpers ----------------

    public static function recomputePayslip(Payslip $slip): Payslip
    {
        $slip->load('lines', 'employee');
        $earnings = round((float) $slip->lines->where('type', PayslipLine::EARNING)->sum('amount'), 3);
        // Deduction lines split: those recovering an advance vs the rest.
        $advanceRecovered = round((float) $slip->lines
            ->where('type', PayslipLine::DEDUCTION)
            ->whereNotNull('employee_advance_id')->sum('amount'), 3);
        $deductions = round((float) $slip->lines
            ->where('type', PayslipLine::DEDUCTION)
            ->whereNull('employee_advance_id')->sum('amount'), 3);

        $gross = round((float) $slip->base_salary + $earnings, 3);

        // Statutory Tunisian charges withheld from the gross: social security
        // (CNSS employee share), income tax (IRPP) and the solidarity levy (CSS).
        $tax = TunisianPayrollTax::compute(
            $gross,
            (bool) ($slip->employee?->head_of_family ?? false),
            (int) ($slip->employee?->dependent_children ?? 0),
        );

        $net = round($gross
            - $tax['cnss_employee'] - $tax['irpp'] - $tax['css']
            - $deductions - $advanceRecovered, 3);

        $slip->update([
            'earnings_total' => $earnings,
            'deductions_total' => $deductions,
            'advance_recovered' => $advanceRecovered,
            'gross_pay' => $gross,
            'cnss_employee' => $tax['cnss_employee'],
            'cnss_employer' => $tax['cnss_employer'],
            'taxable_base' => $tax['taxable_base'],
            'irpp' => $tax['irpp'],
            'css' => $tax['css'],
            'net_pay' => $net,
        ]);

        return $slip->refresh();
    }

    public static function recomputeRun(PayrollRun $run): PayrollRun
    {
        $run->load('payslips');
        $run->update([
            'gross_total' => round((float) $run->payslips->sum('gross_pay'), 3),
            'net_total' => round((float) $run->payslips->sum('net_pay'), 3),
        ]);

        return $run->refresh();
    }
}
