<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One rung of an approval ladder: a role signs it, from a given amount up. */
class ApprovalStep extends Model
{
    protected $fillable = ['workflow_id', 'sequence', 'name', 'approver_role', 'min_amount'];

    protected function casts(): array
    {
        return ['min_amount' => 'decimal:3'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class);
    }
}
