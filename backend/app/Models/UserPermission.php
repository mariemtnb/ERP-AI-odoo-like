<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-user grant or deny, optionally time-boxed — this is what makes
 * "temporary permissions" possible ("cover approvals while the manager is
 * away, until the 30th").
 */
class UserPermission extends Model
{
    protected $fillable = [
        'user_id', 'permission_id', 'allow', 'starts_at', 'expires_at',
        'reason', 'granted_by',
    ];

    protected $attributes = ['allow' => true, 'reason' => ''];

    protected function casts(): array
    {
        return [
            'allow' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** Inside its validity window right now. */
    public function isActive(): bool
    {
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'permission_key' => $this->permission?->key,
            'allow' => $this->allow,
            'starts_at' => $this->starts_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'is_active' => $this->isActive(),
            'reason' => $this->reason,
            'granted_by_email' => $this->grantor?->email,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
