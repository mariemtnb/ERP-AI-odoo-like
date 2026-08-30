<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetEntry extends Model
{
    use Auditable;

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'task_id', 'user_id', 'work_date', 'hours', 'billable', 'note',
    ];

    protected $attributes = ['billable' => true, 'note' => ''];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'hours' => 'decimal:2',
            'billable' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'project' => $this->project_id,
            'task' => $this->task_id,
            'task_name' => $this->task?->name,
            'user_email' => $this->user?->email,
            'work_date' => $this->work_date?->format('Y-m-d'),
            'hours' => $this->hours,
            'billable' => $this->billable,
            'note' => $this->note,
        ];
    }
}
