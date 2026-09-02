<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One document's journey through an approval workflow. */
class ApprovalRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'approvable_type', 'approvable_id', 'workflow_id', 'amount',
        'status', 'current_sequence', 'created_by', 'decided_at',
    ];

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'decided_at' => 'datetime'];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'approvable_type' => class_basename($this->approvable_type),
            'approvable_id' => $this->approvable_id,
            'amount' => (string) $this->amount,
            'status' => $this->status,
            'current_sequence' => $this->current_sequence,
            'decided_at' => $this->decided_at?->toISOString(),
            'actions' => $this->relationLoaded('actions')
                ? $this->actions->map(fn ($a) => $a->toApi())->values()->all()
                : [],
        ];
    }
}
