<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqBid extends Model
{
    use Auditable;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_AWARDED = 'awarded';
    public const STATUS_REJECTED = 'rejected';

    public $timestamps = false;

    protected $fillable = ['rfq_id', 'supplier_id', 'status', 'total_amount', 'note', 'created_by'];

    protected $attributes = ['status' => self::STATUS_SUBMITTED, 'total_amount' => 0, 'note' => ''];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RfqBidLine::class, 'bid_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'rfq' => $this->rfq_id,
            'supplier' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'note' => $this->note,
            'lines' => $this->lines->map(fn ($l) => [
                'rfq_line' => $l->rfq_line_id,
                'unit_price' => $l->unit_price,
            ])->values()->all(),
        ];
    }
}
