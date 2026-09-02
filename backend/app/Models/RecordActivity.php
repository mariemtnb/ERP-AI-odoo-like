<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordActivity extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'title', 'due_date', 'assigned_to', 'done', 'done_at', 'created_by',
    ];

    protected $attributes = ['done' => false];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'done' => 'boolean', 'done_at' => 'datetime'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Not done and due on or before today. */
    public function isOverdue(): bool
    {
        return ! $this->done && $this->due_date && $this->due_date->isPast();
    }

    public function toApi(bool $withSubject = false): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->assignee ? (trim(($this->assignee->first_name ?? '').' '.($this->assignee->last_name ?? '')) ?: $this->assignee->email) : null,
            'done' => $this->done,
            'overdue' => $this->isOverdue(),
            'created_at' => $this->created_at?->toISOString(),
        ];
        if ($withSubject) {
            $data['subject_type'] = $this->subject_type;
            $data['subject_id'] = $this->subject_id;
        }

        return $data;
    }
}
