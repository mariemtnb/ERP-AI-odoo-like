<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseClaim extends Model
{
    use Auditable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REIMBURSED = 'reimbursed';

    public $timestamps = false;

    protected $fillable = [
        'employee_id', 'claim_date', 'category', 'amount', 'description',
        'status', 'decided_by', 'decided_at',
    ];

    protected $attributes = ['status' => self::STATUS_PENDING, 'category' => '', 'description' => ''];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
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
            'claim_date' => $this->claim_date?->format('Y-m-d'),
            'category' => $this->category,
            'amount' => $this->amount,
            'description' => $this->description,
            'status' => $this->status,
            'decided_by_email' => $this->decider?->email,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
