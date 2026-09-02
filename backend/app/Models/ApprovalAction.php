<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single approve/reject decision on one step of a request. */
class ApprovalAction extends Model
{
    protected $fillable = [
        'approval_request_id', 'step_sequence', 'approver_id', 'decision', 'comment', 'acted_at',
    ];

    protected $attributes = ['comment' => ''];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'step_sequence' => $this->step_sequence,
            'approver_id' => $this->approver_id,
            'approver_email' => $this->approver?->email,
            'decision' => $this->decision,
            'comment' => $this->comment,
            'acted_at' => $this->acted_at?->toISOString(),
        ];
    }
}
