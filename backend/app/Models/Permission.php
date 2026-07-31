<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A single capability, keyed as "subject.ability" (e.g. `sales.confirm`). */
class Permission extends Model
{
    protected $fillable = ['key', 'name', 'module', 'description', 'is_approval'];

    protected $attributes = ['module' => 'core', 'description' => '', 'is_approval' => false];

    protected function casts(): array
    {
        return ['is_approval' => 'boolean'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withPivot('allow');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(PermissionGroup::class, 'permission_group_items');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'module' => $this->module,
            'description' => $this->description,
            'is_approval' => $this->is_approval,
        ];
    }
}
