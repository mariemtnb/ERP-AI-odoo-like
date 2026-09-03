<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A stage in the sales pipeline, with a default win probability. */
class CrmStage extends Model
{
    protected $fillable = ['name', 'sequence', 'probability', 'is_won', 'is_lost', 'is_active'];

    protected $attributes = ['sequence' => 0, 'probability' => 0, 'is_won' => false, 'is_lost' => false, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_won' => 'boolean', 'is_lost' => 'boolean', 'is_active' => 'boolean'];
    }

    /** An open stage is neither won nor lost. */
    public function isOpen(): bool
    {
        return ! $this->is_won && ! $this->is_lost;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sequence' => $this->sequence,
            'probability' => $this->probability,
            'is_won' => $this->is_won,
            'is_lost' => $this->is_lost,
        ];
    }
}
