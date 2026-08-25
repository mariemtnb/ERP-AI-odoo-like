<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single line on a payslip: a bonus/earning, or a deduction. */
class PayslipLine extends Model
{
    public const EARNING = 'earning';
    public const DEDUCTION = 'deduction';

    protected $fillable = [
        'payslip_id', 'type', 'label', 'amount', 'is_bonus', 'employee_advance_id',
    ];

    protected $attributes = ['is_bonus' => false];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'is_bonus' => 'boolean'];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'amount' => (string) $this->amount,
            'is_bonus' => $this->is_bonus,
            'employee_advance_id' => $this->employee_advance_id,
        ];
    }
}
