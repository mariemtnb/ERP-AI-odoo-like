<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company bank account. `gl_account_id` lets each account post to its own
 * general-ledger account; when unset the `bank` mapping key is used.
 */
class BankAccount extends Model
{
    protected $fillable = [
        'bank_id', 'label', 'branch', 'rib', 'iban', 'account_number', 'currency',
        'gl_account_id', 'opening_balance', 'opening_date', 'current_balance',
        'last_reconciled_at', 'is_default', 'is_active',
    ];

    protected $attributes = [
        'branch' => '', 'rib' => '', 'iban' => '', 'account_number' => '',
        'currency' => 'TND', 'opening_balance' => 0, 'current_balance' => 0,
        'is_default' => false, 'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'opening_date' => 'date:Y-m-d',
            'last_reconciled_at' => 'date:Y-m-d',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public static function default(): ?self
    {
        return static::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /** Masked RIB for list views — full value stays available on detail. */
    public function maskedRib(): string
    {
        $rib = preg_replace('/\s+/', '', (string) $this->rib);
        if (strlen($rib) <= 6) {
            return $rib;
        }

        return substr($rib, 0, 2) . str_repeat('•', max(0, strlen($rib) - 6)) . substr($rib, -4);
    }

    public function toApi(bool $full = false): array
    {
        return [
            'id' => $this->id,
            'bank_id' => $this->bank_id,
            'bank_name' => $this->bank?->short_name ?: $this->bank?->name,
            'label' => $this->label,
            'branch' => $this->branch,
            'rib' => $full ? $this->rib : $this->maskedRib(),
            'iban' => $full ? $this->iban : '',
            'account_number' => $full ? $this->account_number : '',
            'currency' => $this->currency,
            'gl_account_id' => $this->gl_account_id,
            'gl_account_code' => $this->glAccount?->code,
            'opening_balance' => (string) $this->opening_balance,
            'opening_date' => $this->opening_date?->format('Y-m-d'),
            'current_balance' => (string) $this->current_balance,
            'last_reconciled_at' => $this->last_reconciled_at?->format('Y-m-d'),
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];
    }
}
