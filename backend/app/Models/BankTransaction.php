<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of a bank statement (imported or keyed in). `amount` is signed:
 * positive = money in (credit), negative = money out (debit).
 */
class BankTransaction extends Model
{
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_PARTIAL = 'partially_matched';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_IGNORED = 'ignored';

    public const STATUSES = [
        self::STATUS_UNMATCHED, self::STATUS_PARTIAL, self::STATUS_MATCHED,
        self::STATUS_DISPUTED, self::STATUS_IGNORED,
    ];

    protected $fillable = [
        'bank_account_id', 'operation_date', 'value_date', 'label', 'reference',
        'amount', 'running_balance', 'status', 'matched_amount', 'import_batch',
        'source', 'notes', 'created_by',
    ];

    protected $attributes = [
        'label' => '', 'reference' => '', 'status' => self::STATUS_UNMATCHED,
        'matched_amount' => 0, 'import_batch' => '', 'source' => 'manual', 'notes' => '',
    ];

    protected function casts(): array
    {
        return [
            'operation_date' => 'date:Y-m-d',
            'value_date' => 'date:Y-m-d',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }

    public function isCredit(): bool
    {
        return (float) $this->amount >= 0;
    }

    public function remainingAmount(): float
    {
        return round(abs((float) $this->amount) - (float) $this->matched_amount, 3);
    }

    public function toApi(bool $withMatches = false): array
    {
        $data = [
            'id' => $this->id,
            'bank_account_id' => $this->bank_account_id,
            'bank_account_label' => $this->bankAccount?->label,
            'operation_date' => $this->operation_date?->format('Y-m-d'),
            'value_date' => $this->value_date?->format('Y-m-d'),
            'label' => $this->label,
            'reference' => $this->reference,
            'amount' => (string) $this->amount,
            'direction' => $this->isCredit() ? 'credit' : 'debit',
            'running_balance' => $this->running_balance !== null ? (string) $this->running_balance : null,
            'status' => $this->status,
            'matched_amount' => (string) $this->matched_amount,
            'remaining_amount' => number_format($this->remainingAmount(), 3, '.', ''),
            'source' => $this->source,
            'import_batch' => $this->import_batch,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
        if ($withMatches) {
            $data['matches'] = $this->matches->map(fn (ReconciliationMatch $m) => $m->toApi())->values()->all();
        }

        return $data;
    }
}
