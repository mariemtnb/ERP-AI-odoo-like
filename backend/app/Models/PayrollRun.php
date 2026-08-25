<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One month's payroll: a batch of payslips that are approved and paid together. */
class PayrollRun extends Model
{
    use Auditable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';   // posted to the ledger
    public const STATUS_PAID = 'paid';
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_APPROVED, self::STATUS_PAID];

    protected $fillable = [
        'number', 'period_month', 'label', 'status', 'gross_total', 'net_total',
        'journal_entry_id', 'created_by', 'approved_by', 'approved_at', 'paid_at', 'notes',
    ];

    protected $attributes = [
        'label' => '', 'status' => self::STATUS_DRAFT,
        'gross_total' => 0, 'net_total' => 0, 'notes' => '',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toApi(bool $withPayslips = false): array
    {
        $data = [
            'id' => $this->id,
            'number' => $this->number,
            'period_month' => $this->period_month?->format('Y-m-d'),
            'period_label' => $this->period_month?->translatedFormat('F Y'),
            'label' => $this->label,
            'status' => $this->status,
            'gross_total' => (string) $this->gross_total,
            'net_total' => (string) $this->net_total,
            'employee_count' => $this->payslips_count ?? $this->payslips()->count(),
            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry_number' => $this->journalEntry?->number,
            'created_by_email' => $this->creator?->email,
            'approved_at' => $this->approved_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
        if ($withPayslips) {
            $data['payslips'] = $this->payslips->map(fn (Payslip $p) => $p->toApi(true))->values()->all();
        }

        return $data;
    }
}
