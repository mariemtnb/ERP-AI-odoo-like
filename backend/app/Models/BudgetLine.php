<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    protected $fillable = ['budget_id', 'account_code', 'amount', 'notes'];

    protected $attributes = ['notes' => ''];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3'];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_code', 'code');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'account_code' => $this->account_code,
            'account_name' => $this->account?->name,
            'amount' => (string) $this->amount,
            'notes' => $this->notes,
        ];
    }
}
