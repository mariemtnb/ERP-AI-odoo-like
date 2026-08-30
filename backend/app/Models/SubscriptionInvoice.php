<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    public $timestamps = false;

    protected $fillable = ['subscription_id', 'period_start', 'amount', 'issued_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date:Y-m-d', 'amount' => 'decimal:2', 'issued_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'period_start' => $this->period_start?->format('Y-m-d'),
            'amount' => $this->amount,
            'issued_at' => $this->issued_at?->toISOString(),
        ];
    }
}
