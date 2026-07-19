<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Chart-of-accounts entry. */
class Account extends Model
{
    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    public const TYPES = [
        self::TYPE_ASSET, self::TYPE_LIABILITY, self::TYPE_EQUITY,
        self::TYPE_INCOME, self::TYPE_EXPENSE,
    ];

    /** Well-known codes used by the auto-posting rules. */
    public const CASH = '1000';
    public const RECEIVABLE = '1100';
    public const INVENTORY = '1200';
    public const PAYABLE = '2000';
    public const EQUITY = '3000';
    public const REVENUE = '4000';
    public const COGS = '5000';

    protected $fillable = ['code', 'name', 'type', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Assets and expenses increase on the debit side; liabilities, equity
     * and income increase on the credit side.
     */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, [self::TYPE_ASSET, self::TYPE_EXPENSE], true);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'is_active' => $this->is_active,
        ];
    }
}
