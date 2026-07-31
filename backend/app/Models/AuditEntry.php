<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded change. Append-only — the same discipline as the stock ledger
 * and the instrument history: nothing is ever updated or deleted.
 */
class AuditEntry extends Model
{
    public $timestamps = false;

    public const ACTOR_USER = 'user';
    public const ACTOR_AGENT = 'agent';
    public const ACTOR_SYSTEM = 'system';

    public const EVENT_CREATED = 'created';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';
    public const EVENT_EXPORTED = 'exported';
    public const EVENT_APPROVED = 'approved';
    public const EVENT_REJECTED = 'rejected';

    protected $fillable = [
        'event', 'auditable_type', 'auditable_id', 'label', 'user_id', 'actor',
        'old_values', 'new_values', 'changed_fields', 'reason', 'ip',
        'user_agent', 'url', 'method', 'batch_id', 'company_id', 'created_at',
    ];

    protected $attributes = [
        'label' => '', 'actor' => self::ACTOR_USER, 'reason' => '', 'ip' => '',
        'user_agent' => '', 'url' => '', 'method' => '', 'batch_id' => '',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Short human sentence, used by the activity timeline. */
    public function summary(): string
    {
        $who = $this->actor === self::ACTOR_AGENT
            ? 'AI assistant'
            : ($this->user?->email ?? 'system');

        $what = match ($this->event) {
            self::EVENT_CREATED => 'created',
            self::EVENT_UPDATED => 'updated ' . implode(', ', $this->changed_fields ?? []),
            self::EVENT_DELETED => 'deleted',
            self::EVENT_EXPORTED => 'exported',
            self::EVENT_APPROVED => 'approved',
            self::EVENT_REJECTED => 'rejected',
            default => $this->event,
        };

        return trim("{$who} {$what}");
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'label' => $this->label,
            'user_id' => $this->user_id,
            'user_email' => $this->user?->email,
            'actor' => $this->actor,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'changed_fields' => $this->changed_fields,
            'summary' => $this->summary(),
            'reason' => $this->reason,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'url' => $this->url,
            'method' => $this->method,
            'batch_id' => $this->batch_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
