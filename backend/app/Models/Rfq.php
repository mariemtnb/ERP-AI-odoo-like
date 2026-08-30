<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfq extends Model
{
    use Auditable;

    public const STATUS_OPEN = 'open';
    public const STATUS_AWARDED = 'awarded';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['number', 'title', 'status', 'due_date', 'created_by'];

    protected $attributes = ['status' => self::STATUS_OPEN];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RfqLine::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(RfqBid::class);
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
            'title' => $this->title,
            'status' => $this->status,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'created_by_email' => $this->creator?->email,
            'lines' => $this->lines->map(fn ($l) => $l->toApi())->values()->all(),
            'bids_count' => $this->bids()->count(),
        ];
    }
}
