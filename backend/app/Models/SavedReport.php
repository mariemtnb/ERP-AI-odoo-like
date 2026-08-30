<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    use Auditable;

    protected $fillable = ['name', 'source', 'group_by', 'measure', 'created_by'];

    protected $attributes = ['source' => 'sales'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source' => $this->source,
            'group_by' => $this->group_by,
            'measure' => $this->measure,
            'created_by_email' => $this->creator?->email,
        ];
    }
}
