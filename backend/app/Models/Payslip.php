<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One employee's pay for one run.
 *
 *   gross_pay = base_salary + earnings (bonuses / primes)
 *   net_pay   = gross_pay − deductions − advance_recovered
 */
class Payslip extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'base_salary', 'earnings_total',
        'deductions_total', 'advance_recovered', 'gross_pay',
        'cnss_employee', 'cnss_employer', 'taxable_base', 'irpp', 'css',
        'net_pay', 'status', 'notes',
    ];

    protected $attributes = [
        'base_salary' => 0, 'earnings_total' => 0, 'deductions_total' => 0,
        'advance_recovered' => 0, 'gross_pay' => 0,
        'cnss_employee' => 0, 'cnss_employer' => 0, 'taxable_base' => 0, 'irpp' => 0, 'css' => 0,
        'net_pay' => 0, 'status' => 'draft', 'notes' => '',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }

    public function toApi(bool $withLines = false): array
    {
        $data = [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->fullName(),
            'base_salary' => (string) $this->base_salary,
            'earnings_total' => (string) $this->earnings_total,
            'deductions_total' => (string) $this->deductions_total,
            'advance_recovered' => (string) $this->advance_recovered,
            'gross_pay' => (string) $this->gross_pay,
            'cnss_employee' => (string) $this->cnss_employee,
            'cnss_employer' => (string) $this->cnss_employer,
            'taxable_base' => (string) $this->taxable_base,
            'irpp' => (string) $this->irpp,
            'css' => (string) $this->css,
            'net_pay' => (string) $this->net_pay,
            'status' => $this->status,
            'notes' => $this->notes,
        ];
        if ($withLines) {
            $data['lines'] = $this->lines->map(fn (PayslipLine $l) => $l->toApi())->values()->all();
        }

        return $data;
    }
}
