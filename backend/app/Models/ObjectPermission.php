<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row-level rule: "this role may only approve purchases of branch 3".
 *
 * Object rules narrow an existing permission, never widen one — see
 * PermissionService::canOn.
 */
class ObjectPermission extends Model
{
    public const ABILITIES = ['view', 'create', 'update', 'delete', 'approve'];

    protected $fillable = [
        'user_id', 'role_id', 'subject_type', 'subject_id', 'ability',
        'allow', 'company_id', 'branch_id',
    ];

    protected $attributes = ['allow' => true];

    protected function casts(): array
    {
        return ['allow' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_email' => $this->user?->email,
            'role_id' => $this->role_id,
            'role_key' => $this->role?->key,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'ability' => $this->ability,
            'allow' => $this->allow,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
        ];
    }
}
