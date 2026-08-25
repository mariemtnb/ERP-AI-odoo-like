<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A person on the payroll. May be linked to a login (users) but need not be —
 * plenty of employees never sign in to the ERP.
 */
class Employee extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'user_id', 'first_name', 'last_name', 'job_title', 'department',
        'base_salary', 'currency', 'hire_date', 'end_date', 'phone', 'email',
        'rib', 'bank_account_id', 'is_active', 'notes',
    ];

    protected $attributes = [
        'last_name' => '', 'job_title' => '', 'department' => '', 'base_salary' => 0,
        'currency' => 'TND', 'phone' => '', 'email' => '', 'rib' => '',
        'is_active' => true, 'notes' => '',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:3',
            'hire_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class)->orderByDesc('id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class)->orderByDesc('id');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** Advances paid but not yet fully taken back out of a payslip. */
    public function outstandingAdvance(): float
    {
        // Plain arithmetic works the same on sqlite (tests) and postgres (prod).
        return round((float) $this->advances()
            ->where('status', EmployeeAdvance::STATUS_PAID)
            ->sum(DB::raw('amount - recovered_amount')), 3);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'job_title' => $this->job_title,
            'department' => $this->department,
            'base_salary' => (string) $this->base_salary,
            'currency' => $this->currency,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'phone' => $this->phone,
            'email' => $this->email,
            'rib' => $this->rib,
            'bank_account_id' => $this->bank_account_id,
            'is_active' => $this->is_active,
            'outstanding_advance' => number_format($this->outstandingAdvance(), 3, '.', ''),
            'notes' => $this->notes,
        ];
    }
}
