<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use Auditable;

    public $timestamps = false;

    protected $fillable = [
        'number', 'sale_id', 'customer_id', 'reason', 'total_amount', 'restocked', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'restocked' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'sale' => $this->sale_id,
            'sale_number' => $this->sale?->number,
            'customer' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'reason' => $this->reason,
            'total_amount' => $this->total_amount,
            'restocked' => $this->restocked,
            'created_by_email' => $this->creator?->email,
            'lines' => $this->lines->map(fn ($l) => $l->toApi())->values()->all(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
