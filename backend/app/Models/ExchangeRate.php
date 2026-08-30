<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use Auditable;

    protected $fillable = ['currency_code', 'rate', 'as_of', 'created_by'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:8', 'as_of' => 'date:Y-m-d'];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency_code,
            'rate' => $this->rate,
            'as_of' => $this->as_of?->format('Y-m-d'),
        ];
    }
}
