<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step of a BOM's routing: a task at a work centre for a number of minutes. */
class RoutingOperation extends Model
{
    protected $fillable = ['bom_id', 'sequence', 'name', 'work_centre_id', 'minutes'];

    protected $attributes = ['sequence' => 0, 'minutes' => 0];

    protected function casts(): array
    {
        return ['minutes' => 'decimal:2'];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class, 'bom_id');
    }

    public function workCentre(): BelongsTo
    {
        return $this->belongsTo(WorkCentre::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'name' => $this->name,
            'work_centre_id' => $this->work_centre_id,
            'work_centre_name' => $this->workCentre?->name,
            'minutes' => (string) $this->minutes,
        ];
    }
}
