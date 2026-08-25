<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An advance on salary ("avance sur salaire") — the employee takes part of
 * their pay before the usual date, e.g. for sickness or a family matter.
 *
 * Paying it moves money now; it is taken back out of the next payslip(s).
 */
class EmployeeAdvance extends Model
{
    use Auditable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';          // approved and money handed over
    public const STATUS_RECOVERED = 'recovered'; // fully taken back from payslips
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [
        self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_RECOVERED, self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'number', 'employee_id', 'amount', 'request_date', 'reason', 'method',
        'bank_account_id', 'status', 'paid_at', 'recovered_amount',
        'journal_entry_id', 'created_by', 'approved_by',
    ];

    protected $attributes = [
        'reason' => '', 'method' => 'cash', 'status' => self::STATUS_PENDING,
        'recovered_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'recovered_amount' => 'decimal:3',
            'request_date' => 'date:Y-m-d',
            'paid_at' => 'date:Y-m-d',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function remaining(): float
    {
        return round((float) $this->amount - (float) $this->recovered_amount, 3);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->fullName(),
            'amount' => (string) $this->amount,
            'request_date' => $this->request_date?->format('Y-m-d'),
            'reason' => $this->reason,
            'method' => $this->method,
            'bank_account_id' => $this->bank_account_id,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->format('Y-m-d'),
            'recovered_amount' => (string) $this->recovered_amount,
            'remaining' => number_format($this->remaining(), 3, '.', ''),
            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry_number' => $this->journalEntry?->number,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
