<?php

namespace App\Services;

use App\Models\FieldPermission;
use App\Models\ObjectPermission;
use App\Models\Permission;
use App\Models\PermissionAudit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Permission evaluation.
 *
 * Resolution order, first decisive answer wins:
 *
 *   1. user_permissions  — explicit per-user grant/deny, optionally time-boxed
 *   2. role lineage      — the user's role and everything it inherits
 *   3. deny by default
 *
 * Within a level a DENY always beats an ALLOW, so an inherited grant can be
 * revoked without unpicking the hierarchy.
 *
 * The engine is additive: `users.role` remains the source of truth for which
 * built-in role a user holds, so the existing `role:` middleware and every
 * current route keep behaving exactly as before.
 */
class PermissionService
{
    /** Per-request memo: [userId => [permissionKey => bool]]. */
    private static array $cache = [];

    public static function flush(?int $userId = null): void
    {
        if ($userId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$userId]);
        }
    }

    // ---------------- core check ----------------

    /** Does this user hold `$key` (e.g. "sales.confirm")? */
    public static function has(User $user, string $key): bool
    {
        if (isset(self::$cache[$user->id][$key])) {
            return self::$cache[$user->id][$key];
        }

        $result = self::evaluate($user, $key);
        self::$cache[$user->id][$key] = $result;

        return $result;
    }

    public static function hasAny(User $user, array $keys): bool
    {
        foreach ($keys as $key) {
            if (self::has($user, $key)) {
                return true;
            }
        }

        return false;
    }

    public static function hasAll(User $user, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! self::has($user, $key)) {
                return false;
            }
        }

        return true;
    }

    private static function evaluate(User $user, string $key): bool
    {
        $permission = Permission::where('key', $key)->first();
        if (! $permission) {
            // An unknown key is a programming error, not an open door.
            return false;
        }

        // 1. Per-user override, honouring the validity window.
        $override = UserPermission::where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->get()
            ->filter(fn (UserPermission $p) => $p->isActive())
            // A deny among overlapping grants wins.
            ->sortBy('allow')
            ->first();
        if ($override) {
            return (bool) $override->allow;
        }

        // 2. Roles: the built-in from users.role, plus any custom assignments.
        $roleIds = self::roleIdsFor($user);
        if (! $roleIds) {
            return false;
        }

        $grants = DB::table('role_permissions')
            ->whereIn('role_id', $roleIds)
            ->where(function ($q) use ($permission) {
                $q->where('permission_id', $permission->id)
                    ->orWhereIn('permission_group_id', function ($sub) use ($permission) {
                        $sub->select('permission_group_id')
                            ->from('permission_group_items')
                            ->where('permission_id', $permission->id);
                    });
            })
            ->pluck('allow');

        if ($grants->isEmpty()) {
            return false;
        }

        // An explicit deny anywhere in the lineage revokes the grant.
        return ! $grants->contains(fn ($allow) => ! $allow);
    }

    /** Every role id in play for a user: their built-in role plus its ancestors. */
    public static function roleIdsFor(User $user): array
    {
        $ids = [];

        $base = Role::where('key', $user->role)->first();
        if ($base) {
            $ids = array_map(fn (Role $r) => $r->id, $base->lineage());
        }

        // Additional (custom) roles, ignoring expired assignments.
        $extra = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('role_id');

        foreach ($extra as $roleId) {
            $role = Role::find($roleId);
            if ($role) {
                foreach ($role->lineage() as $ancestor) {
                    $ids[] = $ancestor->id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Roles the user holds *directly* — no lineage walk.
     *
     * Restrictions (object and field rules) resolve against these, never
     * against the inherited chain. Inheritance propagates capability upward:
     * an admin has everything an employee has. Propagating a *restriction* the
     * same way would invert the hierarchy — hiding a column from employees
     * would hide it from their managers too.
     */
    public static function directRoleIdsFor(User $user): array
    {
        $ids = [];

        if ($base = Role::where('key', $user->role)->first()) {
            $ids[] = $base->id;
        }

        $extra = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('role_id')
            ->all();

        return array_values(array_unique(array_merge($ids, $extra)));
    }

    /** Flat list of permission keys, for the frontend to drive its UI. */
    public static function keysFor(User $user): array
    {
        $roleIds = self::roleIdsFor($user);

        $direct = DB::table('role_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->whereIn('rp.role_id', $roleIds)
            ->where('rp.allow', true)
            ->pluck('p.key');

        $viaGroups = DB::table('role_permissions as rp')
            ->join('permission_group_items as gi', 'gi.permission_group_id', '=', 'rp.permission_group_id')
            ->join('permissions as p', 'p.id', '=', 'gi.permission_id')
            ->whereIn('rp.role_id', $roleIds)
            ->where('rp.allow', true)
            ->pluck('p.key');

        $denied = DB::table('role_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->whereIn('rp.role_id', $roleIds)
            ->where('rp.allow', false)
            ->pluck('p.key');

        $keys = $direct->merge($viaGroups)->unique()->diff($denied);

        // Per-user overrides last: they beat everything above.
        foreach (UserPermission::with('permission')->where('user_id', $user->id)->get() as $override) {
            if (! $override->isActive() || ! $override->permission) {
                continue;
            }
            $keys = $override->allow
                ? $keys->push($override->permission->key)
                : $keys->reject(fn ($k) => $k === $override->permission->key);
        }

        return $keys->unique()->values()->all();
    }

    // ---------------- object level ----------------

    /**
     * Can this user perform `$ability` on this specific record?
     *
     * Object rules narrow an existing permission — they never widen one. A
     * user who cannot `sales.update` at all is not rescued by an object grant.
     */
    public static function canOn(User $user, string $ability, Model $subject): bool
    {
        $type = self::subjectType($subject);

        if (! self::has($user, "{$type}.{$ability}")) {
            return false;
        }

        // Direct roles only — a restriction must not travel up the hierarchy.
        $rules = ObjectPermission::where('subject_type', $type)
            ->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhereIn('role_id', self::directRoleIdsFor($user)))
            ->where('ability', $ability)
            ->where(fn ($q) => $q
                ->whereNull('subject_id')
                ->orWhere('subject_id', $subject->getKey()))
            ->get();

        if ($rules->isEmpty()) {
            return true;   // no narrowing rule: the blanket permission stands
        }

        // Most specific rule wins; a deny at the same specificity wins.
        $specific = $rules->where('subject_id', $subject->getKey());
        $applicable = $specific->isNotEmpty() ? $specific : $rules;

        return ! $applicable->contains(fn (ObjectPermission $r) => ! $r->allow);
    }

    /** Model → subject key used by permissions ("sales", "customers"). */
    public static function subjectType(Model|string $subject): string
    {
        $class = is_string($subject) ? $subject : $subject::class;

        return match (class_basename($class)) {
            'Sale' => 'sales',
            'PurchaseOrder' => 'purchases',
            'Customer' => 'customers',
            'Supplier' => 'suppliers',
            'Product' => 'products',
            'Category' => 'categories',
            'StockMovement' => 'stock',
            'Warehouse' => 'warehouses',
            'Lead' => 'leads',
            'JournalEntry' => 'accounting',
            'PaymentInstrument' => 'instruments',
            'InstallmentPlan', 'Installment' => 'installments',
            'Payment' => 'payments',
            'Bank', 'BankAccount' => 'banks',
            'BankTransaction' => 'reconciliation',
            'User' => 'users',
            default => strtolower(class_basename($class)),
        };
    }

    // ---------------- field level ----------------

    /**
     * Fields this user may not see on a given subject.
     *
     * @return array{hidden: string[], readonly: string[]}
     */
    public static function fieldAccess(User $user, Model|string $subject): array
    {
        $type = self::subjectType($subject);

        // Direct roles only, for the same reason as object rules: hiding a
        // column from employees must not hide it from their manager.
        $rules = FieldPermission::where('subject_type', $type)
            ->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhereIn('role_id', self::directRoleIdsFor($user)))
            ->get();

        $hidden = [];
        $readonly = [];
        foreach ($rules as $rule) {
            // An explicit "visible" re-grant beats a role-level hide.
            if ($rule->access === FieldPermission::VISIBLE) {
                $hidden = array_diff($hidden, [$rule->field]);
                $readonly = array_diff($readonly, [$rule->field]);

                continue;
            }
            if ($rule->access === FieldPermission::HIDDEN) {
                $hidden[] = $rule->field;
            } else {
                $readonly[] = $rule->field;
            }
        }

        return [
            'hidden' => array_values(array_unique($hidden)),
            'readonly' => array_values(array_unique($readonly)),
        ];
    }

    /** Strip fields this user may not see from an API payload. */
    public static function filterFields(User $user, Model|string $subject, array $payload): array
    {
        foreach (self::fieldAccess($user, $subject)['hidden'] as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }

    // ---------------- mutation, always audited ----------------

    public static function grantToUser(
        User $target,
        string $permissionKey,
        User $actor,
        bool $allow = true,
        ?string $expiresAt = null,
        string $reason = '',
    ): UserPermission {
        $permission = Permission::where('key', $permissionKey)->firstOrFail();

        return DB::transaction(function () use ($target, $permission, $actor, $allow, $expiresAt, $reason, $permissionKey) {
            $grant = UserPermission::updateOrCreate(
                ['user_id' => $target->id, 'permission_id' => $permission->id],
                [
                    'allow' => $allow,
                    'starts_at' => now(),
                    'expires_at' => $expiresAt,
                    'reason' => $reason,
                    'granted_by' => $actor->id,
                ]
            );

            PermissionAudit::record(
                action: $allow ? 'grant' : 'revoke',
                actor: $actor,
                targetUser: $target,
                permissionKey: $permissionKey,
                reason: $reason,
                detail: $expiresAt ? "expires {$expiresAt}" : 'no expiry',
            );

            self::flush($target->id);

            return $grant;
        });
    }

    public static function revokeFromUser(User $target, string $permissionKey, User $actor, string $reason = ''): void
    {
        $permission = Permission::where('key', $permissionKey)->firstOrFail();

        DB::transaction(function () use ($target, $permission, $actor, $permissionKey, $reason) {
            UserPermission::where('user_id', $target->id)
                ->where('permission_id', $permission->id)
                ->delete();

            PermissionAudit::record(
                action: 'override_removed',
                actor: $actor,
                targetUser: $target,
                permissionKey: $permissionKey,
                reason: $reason,
            );

            self::flush($target->id);
        });
    }

    /** Replace a role's permission set wholesale. */
    public static function syncRolePermissions(Role $role, array $permissionKeys, User $actor): Role
    {
        return DB::transaction(function () use ($role, $permissionKeys, $actor) {
            $ids = Permission::whereIn('key', $permissionKeys)->pluck('id');
            $role->permissions()->sync($ids->mapWithKeys(fn ($id) => [$id => ['allow' => true]])->all());

            PermissionAudit::record(
                action: 'role_permissions_synced',
                actor: $actor,
                role: $role,
                detail: count($permissionKeys) . ' permissions',
            );

            // A role change can affect anyone, so drop the whole memo.
            self::flush();

            return $role->refresh();
        });
    }
}
