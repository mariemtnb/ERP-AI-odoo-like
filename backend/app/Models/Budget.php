<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A budget: a named period with a planned amount per GL account. Actuals are
 * read from the ledger, never stored here, so a budget never drifts from the
 * books.
 */
class Budget extends Model
{
    use Auditable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_CLOSED];

    protected $fillable = [
        'name', 'period_start', 'period_end', 'status', 'notes', 'created_by',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'notes' => ''];

    protected function casts(): array
    {
        return ['period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'period_start' => $this->period_start?->format('Y-m-d'),
            'period_end' => $this->period_end?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'lines' => $this->relationLoaded('lines')
                ? $this->lines->map(fn ($l) => $l->toApi())->values()->all()
                : [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
