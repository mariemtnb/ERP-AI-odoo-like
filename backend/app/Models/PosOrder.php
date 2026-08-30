<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrder extends Model
{
    use Auditable;

    public const STATUS_PAID = 'paid';
    public const STATUS_REFUNDED = 'refunded';

    public $timestamps = false;

    protected $fillable = [
        'number', 'session_id', 'customer_id', 'status',
        'total_amount', 'paid_amount', 'change_due', 'created_by',
    ];

    protected $attributes = ['status' => self::STATUS_PAID, 'total_amount' => 0];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_due' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PosOrderLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class);
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
            'session' => $this->session_id,
            'customer' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'change_due' => $this->change_due,
            'created_by_email' => $this->creator?->email,
            'lines' => $this->lines->map(fn ($l) => $l->toApi())->values()->all(),
            'payments' => $this->payments->map(fn ($p) => [
                'method' => $p->method, 'amount' => $p->amount,
            ])->values()->all(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
