<?php

namespace App\Http\Controllers;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'first_name' => ['sometimes', 'string', 'max:150'],
            'last_name' => ['sometimes', 'string', 'max:150'],
            'role' => ['sometimes', Rule::in(['admin', 'manager', 'employee'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create($data);

        return response()->json($user->toApi(), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'first_name' => ['sometimes', 'string', 'max:150'],
            'last_name' => ['sometimes', 'string', 'max:150'],
            'role' => ['sometimes', Rule::in(['admin', 'manager', 'employee'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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
