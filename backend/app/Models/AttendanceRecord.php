<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use Auditable;

    public $timestamps = false;

    protected $fillable = [
        'employee_id', 'work_date', 'check_in', 'check_out', 'hours', 'note',
    ];

    protected function casts(): array
    {
        return ['work_date' => 'date:Y-m-d', 'hours' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'employee' => $this->employee_id,
            'employee_name' => $this->employee?->fullName(),
            'work_date' => $this->work_date?->format('Y-m-d'),
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'hours' => $this->hours,
            'note' => $this->note,
        ];
    }
}
