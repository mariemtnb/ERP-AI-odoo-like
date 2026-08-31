<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin-only user administration. Deactivation instead of deletion. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->orderBy('id');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('email', 'ilike', "%{$search}%")
                ->orWhere('first_name', 'ilike', "%{$search}%")
                ->orWhere('last_name', 'ilike', "%{$search}%"));
        }
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }
        if (! is_null($request->query('is_active'))) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (User $u) => $u->toApi())
        );
    }

    public function show(User $user)
    {
        return response()->json($user->toApi());
    }

    /** Only a super admin may grant the admin or super_admin role. */
    private const PRIVILEGED_ROLES = [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN];

    private function deniesRole(Request $request, ?string $role): bool
    {
        return $role !== null
            && in_array($role, self::PRIVILEGED_ROLES, true)
            && ! $request->user()->isSuperAdmin();
    }

    /** Built-in roles plus every custom role key — what may be assigned. */
    private function assignableRoles(): array
    {
        return array_values(array_unique(array_merge(
            User::ROLES,
            Role::where('is_system', false)
                ->whereNotIn('key', Role::SYSTEM_ROLES)
                ->pluck('key')->all()
        )));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'first_name' => ['sometimes', 'string', 'max:150'],
            'last_name' => ['sometimes', 'string', 'max:150'],
            'role' => ['sometimes', Rule::in($this->assignableRoles())],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($this->deniesRole($request, $data['role'] ?? null)) {
            return response()->json(['detail' => 'Only a super admin can grant admin roles.'], 403);
        }

        $user = User::create($data);

        return response()->json($user->toApi(), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'first_name' => ['sometimes', 'string', 'max:150'],
            'last_name' => ['sometimes', 'string', 'max:150'],
            'role' => ['sometimes', Rule::in($this->assignableRoles())],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // A non-super-admin can neither grant a privileged role nor edit an
        // account that already holds one (so an admin cannot demote/hijack a
        // super admin or a peer admin).
        if ($this->deniesRole($request, $data['role'] ?? null)
            || (in_array($user->role, self::PRIVILEGED_ROLES, true) && ! $request->user()->isSuperAdmin())) {
            return response()->json(['detail' => 'Only a super admin can manage admin accounts.'], 403);
        }

        $user->update($data);

        return response()->json($user->toApi());
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(
                ['detail' => 'You cannot deactivate your own account.'],
                400
            );
        }

        $user->update(['is_active' => false]);

        return response()->json(null, 204);
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate(['new_password' => ['required', 'string', 'min:8']]);
        $user->update(['password' => $data['new_password']]);

        return response()->json(['detail' => 'Password reset.']);
    }
}
