<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A request to purchase, routed through the approval engine and — once fully
 * approved — converted into a purchase order.
 */
class PurchaseRequisition extends Model
{
    use Auditable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'number', 'requested_by', 'supplier_id', 'status', 'total_estimate',
        'notes', 'purchase_order_id', 'created_by',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'total_estimate' => 0, 'notes' => ''];

    protected function casts(): array
    {
        return ['total_estimate' => 'decimal:3'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RequisitionLine::class, 'requisition_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }

    public function recomputeTotal(): void
    {
        $this->total_estimate = $this->lines->sum(fn ($l) => (float) $l->quantity * (float) $l->estimated_price);
        $this->save();
    }

    /** Called by the approval engine when the request is finally decided. */
    public function applyApprovalOutcome(string $requestStatus): void
    {
        $this->update(['status' => $requestStatus === ApprovalRequest::STATUS_APPROVED
            ? self::STATUS_APPROVED
            : self::STATUS_REJECTED]);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'requested_by' => $this->requested_by,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'status' => $this->status,
            'total_estimate' => (string) $this->total_estimate,
            'notes' => $this->notes,
            'purchase_order_id' => $this->purchase_order_id,
            'lines' => $this->relationLoaded('lines')
                ? $this->lines->map(fn ($l) => $l->toApi())->values()->all()
                : [],
            'approval' => $this->relationLoaded('approvalRequest') && $this->approvalRequest
                ? $this->approvalRequest->toApi()
                : null,
        ];
    }
}
