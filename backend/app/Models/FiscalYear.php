<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An accounting period. Closing one is what stops a posting being backdated
 * into books that have already been reported.
 */
class FiscalYear extends Model
{
    use Auditable;

    public const OPEN = 'open';
    public const CLOSED = 'closed';
    public const LOCKED = 'locked';
    public const STATUSES = [self::OPEN, self::CLOSED, self::LOCKED];

    protected $fillable = [
        'company_id', 'name', 'starts_on', 'ends_on', 'status',
        'closed_at', 'closed_by', 'closing_entry_id',
    ];

    protected $attributes = ['status' => self::OPEN];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function contains(string $date): bool
    {
        $d = Carbon::parse($date)->startOfDay();

        return $d->betweenIncluded($this->starts_on, $this->ends_on);
    }

    public function acceptsPostings(): bool
    {
        return $this->status === self::OPEN;
    }

    /** The year covering a date, or null when none is configured. */
    public static function forDate(string $date, ?int $companyId = null): ?self
    {
        return static::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->orderBy('id')
            ->first();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'starts_on' => $this->starts_on?->format('Y-m-d'),
            'ends_on' => $this->ends_on?->format('Y-m-d'),
            'status' => $this->status,
            'accepts_postings' => $this->acceptsPostings(),
            'closed_at' => $this->closed_at?->toISOString(),
            'closed_by_email' => $this->closer?->email,
            'closing_entry_id' => $this->closing_entry_id,
        ];
    }
}
