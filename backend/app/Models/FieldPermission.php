<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Field-level visibility — e.g. hide `cost_price` from employees so the
 * margin is not readable by everyone who can list products.
 */
class FieldPermission extends Model
{
    public const HIDDEN = 'hidden';
    public const READONLY = 'readonly';
    public const VISIBLE = 'visible';
    public const ACCESS = [self::HIDDEN, self::READONLY, self::VISIBLE];

    protected $fillable = ['role_id', 'user_id', 'subject_type', 'field', 'access'];

    protected $attributes = ['access' => self::HIDDEN];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'role_id' => $this->role_id,
            'role_key' => $this->role?->key,
            'user_id' => $this->user_id,
            'subject_type' => $this->subject_type,
            'field' => $this->field,
            'access' => $this->access,
        ];
    }
}
