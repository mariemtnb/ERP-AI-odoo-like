<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'role', 'content', 'tool_calls', 'pending_action', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'pending_action' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'tool_calls' => $this->tool_calls,
            'pending_action' => $this->pending_action,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
