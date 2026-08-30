<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    use Auditable;

    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';

    public $timestamps = false;

    protected $fillable = ['project_id', 'name', 'estimate_hours', 'status'];

    protected $attributes = ['status' => self::STATUS_OPEN];

    protected function casts(): array
    {
        return ['estimate_hours' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'project' => $this->project_id,
            'name' => $this->name,
            'estimate_hours' => $this->estimate_hours,
            'status' => $this->status,
            'logged_hours' => number_format((float) TimesheetEntry::where('task_id', $this->id)->sum('hours'), 2, '.', ''),
        ];
    }
}
