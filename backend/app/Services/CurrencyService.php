<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;

/**
 * Multi-currency conversion. Every rate is the base-currency value of one unit
 * of a currency (base currency = 1). To convert between two non-base
 * currencies we pass through the base:
 *
 *   amount_in_to = amount_in_from * rate(from) / rate(to)
 */
class CurrencyService
{
    public static function base(): ?Currency
    {
        return Currency::where('is_base', true)->first();
    }

    /** Latest rate on or before $date; base currency is always 1. */
    public static function rateFor(string $code, ?string $date = null): float
    {
        $currency = Currency::find($code);
        if (! $currency) {
            throw new InvalidTransition("Unknown currency: {$code}.");
        }
        if ($currency->is_base) {
            return 1.0;
        }

        $date ??= now()->toDateString();
        $rate = ExchangeRate::where('currency_code', $code)
            ->whereDate('as_of', '<=', $date)
            ->orderByDesc('as_of')
            ->orderByDesc('id')
            ->value('rate');

        if ($rate === null) {
            throw new InvalidTransition("No exchange rate set for {$code} on or before {$date}.");
        }

        return (float) $rate;
    }

    /** Latest rate value as a string, or null if none — used for display. */
    public static function latestRateValue(string $code): ?string
    {
        $rate = ExchangeRate::where('currency_code', $code)
            ->orderByDesc('as_of')->orderByDesc('id')->value('rate');

        return $rate === null ? null : (string) $rate;
    }

    public static function convert(float $amount, string $from, string $to, ?string $date = null): float
    {
        if ($from === $to) {
            return round($amount, 8);
        }

        $value = $amount * self::rateFor($from, $date) / self::rateFor($to, $date);

        return round($value, 8);
    }

    public static function setRate(string $code, float $rate, ?string $asOf, User $user): ExchangeRate
    {
        $currency = Currency::find($code);
        if (! $currency) {
            throw new InvalidTransition("Unknown currency: {$code}.");
        }
        if ($currency->is_base) {
            throw new InvalidTransition('The base currency is always 1 — it has no rate.');
        }
        if ($rate <= 0) {
            throw new InvalidTransition('Exchange rate must be positive.');
        }

        return ExchangeRate::create([
            'currency_code' => $code,
            'rate' => $rate,
            'as_of' => $asOf ?? now()->toDateString(),
            'created_by' => $user->id,
        ]);
    }
}
