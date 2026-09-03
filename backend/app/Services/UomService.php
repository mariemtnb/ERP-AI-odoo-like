<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\UnitOfMeasure;

/**
 * Unit-of-measure conversion. Within a category every unit is expressed as a
 * factor of the category's reference unit, so converting is just
 * qty × from.factor ÷ to.factor. Units in different categories cannot convert.
 */
class UomService
{
    public static function convert(float $qty, string $fromCode, string $toCode): float
    {
        if ($fromCode === $toCode) {
            return round($qty, 6);
        }
        $from = UnitOfMeasure::where('code', $fromCode)->first();
        $to = UnitOfMeasure::where('code', $toCode)->first();
        if (! $from || ! $to) {
            throw new InvalidTransition('Unknown unit of measure.');
        }
        if ($from->category !== $to->category) {
            throw new InvalidTransition("Cannot convert {$fromCode} ({$from->category}) to {$toCode} ({$to->category}).");
        }

        return round($qty * (float) $from->factor / (float) $to->factor, 6);
    }
}
