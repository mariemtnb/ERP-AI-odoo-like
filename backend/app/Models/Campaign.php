<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use Auditable;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    protected $fillable = [
        'name', 'channel', 'subject', 'body', 'status', 'sent_count', 'created_by', 'sent_at',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'subject' => '', 'sent_count' => 0];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'sent_count' => 'integer'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'sent_count' => $this->sent_count,
            'created_by_email' => $this->creator?->email,
            'sent_at' => $this->sent_at?->toISOString(),
        ];
    }
}
