<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Tunisian electronic invoice (TEIF) generated from a sale and submitted to
 * TTN. One row per sale; it moves generated → submitted → accepted|rejected.
 */
class EInvoice extends Model
{
    public const STATUS_GENERATED = 'generated';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'sale_id', 'invoice_id', 'provider', 'status', 'ttn_ref', 'xml', 'error',
        'submitted_at', 'accepted_at',
    ];

    protected $attributes = ['status' => self::STATUS_GENERATED, 'provider' => 'mock'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Accepted invoices are final and must not be regenerated or resubmitted. */
    public function isFinal(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function toApi(bool $withXml = false): array
    {
        return array_filter([
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'invoice_id' => $this->invoice_id,
            'provider' => $this->provider,
            'status' => $this->status,
            'ttn_ref' => $this->ttn_ref,
            'error' => $this->error,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'xml' => $withXml ? $this->xml : null,
        ], fn ($v) => $v !== null);
    }
}
