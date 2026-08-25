<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One in-app notification for one user. */
class Notification extends Model
{
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const CRITICAL = 'critical';

    protected $fillable = [
        'user_id', 'type', 'category', 'severity', 'title', 'body', 'link',
        'subject_type', 'subject_id', 'dedupe_key', 'read_at',
    ];

    protected $attributes = [
        'category' => 'system', 'severity' => self::INFO, 'body' => '',
        'link' => '', 'subject_type' => '', 'dedupe_key' => '',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'severity' => $this->severity,
            'title' => $this->title,
            'body' => $this->body,
            'link' => $this->link,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
