<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use Auditable;

    public const STATUSES = ['new', 'contacted', 'qualified', 'won', 'lost'];

    protected $fillable = [
        'name', 'company', 'email', 'phone', 'source', 'status', 'notes',
        'stage_id', 'expected_revenue', 'probability', 'lost_reason',
        'assigned_to', 'customer_id', 'created_by',
    ];

    protected $attributes = [
        'company' => '', 'email' => '', 'phone' => '', 'source' => '',
        'status' => 'new', 'notes' => '', 'expected_revenue' => 0, 'lost_reason' => '',
    ];

    protected function casts(): array
    {
        return ['expected_revenue' => 'decimal:3'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    /** The probability in effect: the lead's own override, else its stage's default. */
    public function effectiveProbability(): int
    {
        return $this->probability ?? (int) ($this->stage?->probability ?? 0);
    }

    /** Expected revenue weighted by the effective probability. */
    public function weightedValue(): float
    {
        return round((float) $this->expected_revenue * $this->effectiveProbability() / 100, 3);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('created_at');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function toApi(bool $withActivities = false): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source,
            'status' => $this->status,
            'stage_id' => $this->stage_id,
            'stage_name' => $this->stage?->name,
            'expected_revenue' => (string) $this->expected_revenue,
            'probability' => $this->effectiveProbability(),
            'weighted_value' => number_format($this->weightedValue(), 3, '.', ''),
            'lost_reason' => $this->lost_reason,
            'notes' => $this->notes,
            'assigned_to' => $this->assigned_to,
            'assigned_to_email' => $this->assignee?->email,
            'customer_id' => $this->customer_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
        if ($withActivities) {
            $data['activities'] = $this->activities->map(fn ($a) => $a->toApi())->values()->all();
        }

        return $data;
    }
}
