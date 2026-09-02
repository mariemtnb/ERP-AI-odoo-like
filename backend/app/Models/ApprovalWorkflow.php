<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The approval ladder for one document type. */
class ApprovalWorkflow extends Model
{
    protected $fillable = ['name', 'document_type', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'workflow_id')->orderBy('sequence');
    }
}
