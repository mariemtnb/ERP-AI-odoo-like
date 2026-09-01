<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorBill extends Model
{
    use Auditable;

    public const STATUS_MATCHED = 'matched';       // agrees with PO + receipt
    public const STATUS_EXCEPTION = 'exception';    // discrepancy, needs approval
    public const STATUS_APPROVED = 'approved';      // exception cleared by a manager
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'number', 'supplier_id', 'purchase_order_id', 'bill_date', 'supplier_ref',
        'total_amount', 'status', 'approved_by', 'approved_at', 'created_by',
    ];

    protected $attributes = ['supplier_ref' => '', 'total_amount' => 0, 'status' => self::STATUS_MATCHED];

    protected function casts(): array
    {
        return ['bill_date' => 'date:Y-m-d', 'total_amount' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VendorBillLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recomputeTotal(): void
    {
        $this->total_amount = $this->lines->sum(fn ($l) => $l->quantity * $l->unit_price);
        $this->save();
    }

    /** True once the bill is cleared for payment (matched or approved). */
    public function isPayable(): bool
    {
        return in_array($this->status, [self::STATUS_MATCHED, self::STATUS_APPROVED], true);
    }

    public function toApi(bool $withLines = false, bool $withMatch = false): array
    {
        $data = [
            'id' => $this->id,
            'number' => $this->number,
            'supplier' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_number' => $this->purchaseOrder?->number,
            'bill_date' => $this->bill_date?->format('Y-m-d'),
            'supplier_ref' => $this->supplier_ref,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'approved_by_email' => $this->approvedBy?->email ?? null,
            'created_at' => $this->created_at?->toISOString(),
        ];
        if ($withLines) {
            $data['lines'] = $this->lines->map(fn (VendorBillLine $l) => $l->toApi())->values()->all();
        }
        if ($withMatch) {
            $data['match'] = \App\Services\VendorBillService::matchReport($this);
        }

        return $data;
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
