<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A banking institution. Seeded with Tunisian banks; fully editable. */
class Bank extends Model
{
    protected $fillable = ['code', 'name', 'short_name', 'swift', 'country', 'is_active'];

    protected $attributes = [
        'short_name' => '', 'swift' => '', 'country' => 'TN', 'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'short_name' => $this->short_name ?: $this->name,
            'swift' => $this->swift,
            'country' => $this->country,
            'is_active' => $this->is_active,
            'account_count' => $this->accounts_count ?? $this->accounts()->count(),
        ];
    }
}
