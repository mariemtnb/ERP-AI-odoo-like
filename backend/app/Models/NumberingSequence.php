<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Document numbering.
 *
 * Replaces the `count()`-based scheme, which had two real defects: two
 * concurrent requests could read the same count and mint duplicate numbers,
 * and deleting a record made the next one reuse a number that had already been
 * issued. A stored counter incremented under a row lock fixes both.
 */
class NumberingSequence extends Model
{
    public const RESET_YEARLY = 'yearly';
    public const RESET_MONTHLY = 'monthly';
    public const RESET_NEVER = 'never';

    protected $fillable = [
        'company_id', 'key', 'name', 'format', 'prefix', 'next_number',
        'reset_period', 'current_period', 'is_active',
    ];

    protected $attributes = [
        'name' => '', 'format' => '{PREFIX}-{YYYY}-{SEQ:4}', 'prefix' => '',
        'next_number' => 1, 'reset_period' => self::RESET_YEARLY,
        'current_period' => '', 'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** The period token the counter resets on. */
    public function periodKey(): string
    {
        return match ($this->reset_period) {
            self::RESET_MONTHLY => now()->format('Y-m'),
            self::RESET_NEVER => 'all',
            default => now()->format('Y'),
        };
    }

    /**
     * Reserve and render the next number.
     *
     * Locks the sequence row so concurrent callers queue rather than collide.
     * Falls back to the legacy prefix-count scheme when no sequence is
     * configured, so nothing breaks if a row is missing.
     */
    public static function next(string $key, string $fallbackPrefix = '', ?string $fallbackModel = null): string
    {
        return DB::transaction(function () use ($key, $fallbackPrefix, $fallbackModel) {
            $sequence = static::where('key', $key)->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                return self::legacyNumber($fallbackPrefix, $fallbackModel);
            }

            $period = $sequence->periodKey();
            if ($sequence->current_period !== $period) {
                $sequence->next_number = 1;
                $sequence->current_period = $period;
            }

            $number = (int) $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->save();

            return $sequence->render($number);
        });
    }

    /** Expand the format tokens for a given counter value. */
    public function render(int $number): string
    {
        $out = $this->format;
        $out = str_replace('{PREFIX}', $this->prefix, $out);
        $out = str_replace('{YYYY}', now()->format('Y'), $out);
        $out = str_replace('{YY}', now()->format('y'), $out);
        $out = str_replace('{MM}', now()->format('m'), $out);

        // {SEQ:4} → zero-padded to 4; bare {SEQ} → unpadded.
        $out = preg_replace_callback(
            '/\{SEQ(?::(\d+))?\}/',
            fn ($m) => isset($m[1]) ? str_pad((string) $number, (int) $m[1], '0', STR_PAD_LEFT) : (string) $number,
            $out
        );

        return $out;
    }

    /** The original scheme, kept as a safety net. */
    private static function legacyNumber(string $prefix, ?string $model): string
    {
        if (! $prefix || ! $model || ! class_exists($model)) {
            return $prefix . '-' . now()->format('Y') . '-0001';
        }

        // Different tables store the document number under different columns
        // (most use `number`, but e.g. employees use `code`). Pick whichever
        // the model's table actually has; if neither is present, skip the
        // count entirely rather than crash on an undefined column.
        $instance = new $model;
        $table = $instance->getTable();
        $column = collect(['number', 'code'])
            ->first(fn ($c) => Schema::hasColumn($table, $c));

        $year = now()->year;

        if (! $column) {
            return sprintf('%s-%d-%04d', $prefix, $year, 1);
        }

        $count = $model::where($column, 'like', "{$prefix}-{$year}-%")->count();

        return sprintf('%s-%d-%04d', $prefix, $year, $count + 1);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'key' => $this->key,
            'name' => $this->name,
            'format' => $this->format,
            'prefix' => $this->prefix,
            'next_number' => (int) $this->next_number,
            'reset_period' => $this->reset_period,
            'current_period' => $this->current_period,
            'is_active' => $this->is_active,
            'preview' => $this->render((int) $this->next_number),
        ];
    }
}
