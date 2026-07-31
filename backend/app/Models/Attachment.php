<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Scanned document attached to an instrument, payment or bank statement. */
class Attachment extends Model
{
    public $timestamps = false;

    public const OWNER_INSTRUMENT = 'instrument';
    public const OWNER_PAYMENT = 'payment';
    public const OWNER_BANK_TRANSACTION = 'bank_transaction';
    public const OWNER_TYPES = [
        self::OWNER_INSTRUMENT, self::OWNER_PAYMENT, self::OWNER_BANK_TRANSACTION,
    ];

    protected $fillable = [
        'owner_type', 'owner_id', 'path', 'filename', 'mime', 'size',
        'uploaded_by', 'created_at',
    ];

    protected $attributes = ['mime' => '', 'size' => 0];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size' => $this->size,
            'download_url' => "/api/v1/attachments/{$this->id}",
            'uploaded_by_email' => $this->uploader?->email,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
