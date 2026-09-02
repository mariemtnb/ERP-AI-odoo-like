<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use Auditable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PARTIAL = 'partial';   // some, but not all, goods received
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'number', 'supplier_id', 'status', 'order_date', 'received_date',
        'total_amount', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'total_amount' => 0];

    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'received_date' => 'date:Y-m-d',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recomputeTotal(): void
    {
        $this->total_amount = $this->lines->sum(fn ($l) => $l->quantity * $l->unit_price);
        $this->save();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'status' => $this->status,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'received_date' => $this->received_date?->format('Y-m-d'),
            'total_amount' => $this->total_amount,
            'created_by_email' => $this->creator?->email,
            'approved_by_email' => $this->approved_by ? $this->approver?->email : null,
            'lines' => $this->lines->map(fn ($l) => $l->toApi())->values()->all(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
