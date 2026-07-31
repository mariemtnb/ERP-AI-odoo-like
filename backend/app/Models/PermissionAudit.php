<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

/**
 * Immutable history of permission changes. Separate from the general audit
 * trail on purpose: "who could do what, when" is the question an auditor asks
 * first, and it should not have to be dug out of a table full of row edits.
 */
class PermissionAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action', 'actor_id', 'target_user_id', 'role_id', 'permission_key',
        'detail', 'reason', 'ip', 'created_at',
    ];

    protected $attributes = ['permission_key' => '', 'detail' => '', 'reason' => '', 'ip' => ''];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public static function record(
        string $action,
        ?User $actor = null,
        ?User $targetUser = null,
        ?Role $role = null,
        string $permissionKey = '',
        string $detail = '',
        string $reason = '',
    ): self {
        return static::create([
            'action' => $action,
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUser?->id,
            'role_id' => $role?->id,
            'permission_key' => $permissionKey,
            'detail' => $detail,
            'reason' => $reason,
            'ip' => self::clientIp(),
            'created_at' => now(),
        ]);
    }

    /** Safe outside an HTTP context (console commands, queued jobs, tests). */
    private static function clientIp(): string
    {
        try {
            return (string) (Request::ip() ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor_email' => $this->actor?->email,
            'target_user_email' => $this->targetUser?->email,
            'role_key' => $this->role?->key,
            'permission_key' => $this->permission_key,
            'detail' => $this->detail,
            'reason' => $this->reason,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
