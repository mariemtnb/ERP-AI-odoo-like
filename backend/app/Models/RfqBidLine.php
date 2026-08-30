<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqBidLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['bid_id', 'rfq_line_id', 'unit_price'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(RfqBid::class, 'bid_id');
    }

    public function rfqLine(): BelongsTo
    {
        return $this->belongsTo(RfqLine::class, 'rfq_line_id');
    }
}
