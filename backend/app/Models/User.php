<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    // Account changes — who created, re-roled or deactivated whom — are
    // exactly what an auditor asks about first. The password is redacted by
    // AuditService before anything is written.
    use Auditable, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_EMPLOYEE = 'employee';

    /** Assignable roles, most privileged first. */
    public const ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_EMPLOYEE];

    protected $fillable = [
        'email', 'password', 'first_name', 'last_name', 'role', 'is_active',
    ];

    protected $hidden = ['password'];

    // Mirrors the column default, so a freshly created instance agrees with
    // what the database will hold. Without it `is_active` is simply absent
    // in memory until the row is re-read, which reads as "not active".
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['role' => $this->role];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** Super admin is a strict superset of admin, so it passes every admin gate. */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    public function isManagerial(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }

    /** DRF-compatible user payload (matches the old Django API). */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'role' => $this->role,
            'is_active' => $this->is_active,
        ];
    }
}
