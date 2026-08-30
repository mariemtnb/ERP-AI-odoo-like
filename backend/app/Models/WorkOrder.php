<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrder extends Model
{
    use Auditable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'number', 'bom_id', 'product_id', 'quantity', 'status',
        'created_by', 'started_at', 'completed_at',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class, 'bom_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
            'bom' => $this->bom_id,
            'product' => $this->product_id,
            'product_name' => $this->product?->name,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'created_by_email' => $this->creator?->email,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
