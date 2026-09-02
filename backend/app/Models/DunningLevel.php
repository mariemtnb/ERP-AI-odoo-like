<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One rung of the dunning ladder: fires once an invoice is N days overdue. */
class DunningLevel extends Model
{
    protected $fillable = ['level', 'days_overdue', 'name', 'message', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'days_overdue' => $this->days_overdue,
            'name' => $this->name,
            'message' => $this->message,
            'is_active' => $this->is_active,
        ];
    }
}
