<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = ['campaign_id', 'customer_id', 'contact', 'status'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function toApi(): array
    {
        return [
            'customer' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'contact' => $this->contact,
            'status' => $this->status,
        ];
    }
}
