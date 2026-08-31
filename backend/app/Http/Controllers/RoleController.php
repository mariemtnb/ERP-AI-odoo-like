<?php

namespace App\Http\Controllers;

use App\Models\PermissionAudit;
use App\Models\Role;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Custom roles with a module allowlist.
 *
 * Reads are open to admins (the users screen needs the list to populate its
 * role picker); writes are super-admin only, matching who is allowed to shape
 * access in the first place. Built-in roles are surfaced but never editable
 * here — their visibility lives in code.
 */
class RoleController extends Controller
{
    /** The catalogue of modules a role can be granted. */
    public function modules()
    {
        return response()->json(['results' => Modules::list()]);
    }

    /** System roles (synthesised) plus every custom role, with head-counts. */
    public function index()
    {
        $counts = User::selectRaw('role, count(*) as c')->groupBy('role')->pluck('c', 'role');

        $custom = Role::where('is_system', false)
            ->whereNotIn('key', Role::SYSTEM_ROLES)
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => $this->present($r, (int) ($counts[$r->key] ?? 0)))
            ->all();

        return response()->json(['results' => array_merge($this->systemRoles($counts), $custom)]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $key = $this->uniqueKey($data['name']);
        $role = Role::create([
            'key' => $key,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'is_system' => false,
            'level' => 10,
            'modules' => Modules::sanitize($data['modules']),
        ]);

        PermissionAudit::record('role_created', $request->user(), role: $role, detail: $key);

        return response()->json($this->present($role, 0), 201);
    }

    public function update(Request $request, Role $role)
    {
        if ($role->is_system || in_array($role->key, Role::SYSTEM_ROLES, true)) {
            return response()->json(['detail' => 'Built-in roles cannot be edited.'], 422);
        }

        $data = $this->validatePayload($request, creating: false);

        if (array_key_exists('name', $data)) {
            $role->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $role->description = $data['description'] ?? '';
        }
        if (array_key_exists('modules', $data)) {
            $role->modules = Modules::sanitize($data['modules']);
        }
        $role->save();

        PermissionAudit::record('role_updated', $request->user(), role: $role, detail: $role->key);

        $count = User::where('role', $role->key)->count();

        return response()->json($this->present($role, $count));
    }

    public function destroy(Request $request, Role $role)
    {
        if ($role->is_system || in_array($role->key, Role::SYSTEM_ROLES, true)) {
            return response()->json(['detail' => 'Built-in roles cannot be deleted.'], 422);
        }

        $inUse = User::where('role', $role->key)->count();
        if ($inUse > 0) {
            return response()->json([
                'detail' => "This role is assigned to {$inUse} user(s). Reassign them before deleting it.",
            ], 422);
        }

        PermissionAudit::record('role_deleted', $request->user(), role: $role, detail: $role->key);
        $role->delete();

        return response()->json(null, 204);
    }

    // ---------------- helpers ----------------

    private function validatePayload(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'modules' => [$creating ? 'required' : 'sometimes', 'array'],
            'modules.*' => ['string', Rule::in(Modules::keys())],
        ]);
    }

    /** Turn a label into a stable, unique slug key. */
    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $key = $base;
        $n = 2;
        while (Role::where('key', $key)->exists()) {
            $key = $base . '_' . $n++;
        }

        return $key;
    }

    private function present(Role $role, int $userCount): array
    {
        return [
            'id' => $role->id,
            'key' => $role->key,
            'name' => $role->name,
            'description' => $role->description,
            'is_system' => false,
            'modules' => $role->modules ?? [],
            'user_count' => $userCount,
        ];
    }

    /**
     * The four built-ins presented uniformly with the custom roles so the UI
     * can list everything together. They are unrestricted (all modules) and
     * not editable.
     */
    private function systemRoles($counts): array
    {
        $all = Modules::keys();
        $rows = [
            ['key' => User::ROLE_SUPER_ADMIN, 'name' => 'Super admin'],
            ['key' => User::ROLE_ADMIN, 'name' => 'Admin'],
            ['key' => User::ROLE_MANAGER, 'name' => 'Manager'],
            ['key' => User::ROLE_EMPLOYEE, 'name' => 'Employee'],
        ];

        return array_map(fn ($r) => [
            'id' => null,
            'key' => $r['key'],
            'name' => $r['name'],
            'description' => 'Built-in role',
            'is_system' => true,
            'modules' => $all,           // shown as full access
            'user_count' => (int) ($counts[$r['key']] ?? 0),
        ], $rows);
    }
}
