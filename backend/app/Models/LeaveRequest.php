<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use Auditable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TYPES = ['annual', 'sick', 'unpaid'];

    public $timestamps = false;

    protected $fillable = [
        'employee_id', 'type', 'start_date', 'end_date', 'days',
        'reason', 'status', 'decided_by', 'decided_at',
    ];

    protected $attributes = ['status' => self::STATUS_PENDING, 'reason' => ''];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'days' => 'decimal:1',
            'decided_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'employee' => $this->employee_id,
            'employee_name' => $this->employee?->fullName(),
            'type' => $this->type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'days' => $this->days,
            'reason' => $this->reason,
            'status' => $this->status,
            'decided_by_email' => $this->decider?->email,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
