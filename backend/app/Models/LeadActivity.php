<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    public $timestamps = false;

    protected $fillable = ['lead_id', 'type', 'summary', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'summary' => $this->summary,
            'created_by_email' => $this->creator?->email,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
