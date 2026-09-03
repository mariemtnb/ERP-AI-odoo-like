<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A place or machine where an operation happens, with an hourly cost. */
class WorkCentre extends Model
{
    protected $fillable = ['code', 'name', 'cost_per_hour', 'capacity_minutes_per_day', 'is_active'];

    protected $attributes = ['cost_per_hour' => 0, 'capacity_minutes_per_day' => 480, 'is_active' => true];

    protected function casts(): array
    {
        return ['cost_per_hour' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'cost_per_hour' => (string) $this->cost_per_hour,
            'capacity_minutes_per_day' => $this->capacity_minutes_per_day,
            'is_active' => $this->is_active,
        ];
    }
}
