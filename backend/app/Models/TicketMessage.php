<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['ticket_id', 'user_id', 'body'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'user_email' => $this->user?->email,
            'body' => $this->body,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
