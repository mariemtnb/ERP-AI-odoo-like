<?php

namespace App\Http\Controllers;

use App\Models\AuditEntry;
use App\Models\Branch;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\FeatureFlag;
use App\Models\FieldPermission;
use App\Models\FiscalYear;
use App\Models\NumberingSequence;
use App\Models\ObjectPermission;
use App\Models\Permission;
use App\Models\PermissionAudit;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Administration: organisation structure, permissions, audit and feature
 * flags. Everything here is admin-only (see routes/api.php).
 */
class AdministrationController extends Controller
{
    // ---------------- companies & structure ----------------

    public function companies(Request $request)
    {
        return response()->json([
            'results' => Company::with('parent')->withCount('branches')->orderBy('name')
                ->get()->map(fn (Company $c) => $c->toApi())->values()->all(),
        ]);
    }

    public function storeCompany(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:companies,code'],
            'name' => ['required', 'string', 'max:200'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'locale' => ['sometimes', 'string', 'max:5'],
            'timezone' => ['sometimes', 'string', 'max:64'],
        ]);
        $data['legal_name'] ??= '';

        return response()->json(Company::create($data)->toApi(), 201);
    }

    public function updateCompany(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'locale' => ['sometimes', 'string', 'max:5'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($company, $data) {
            $company->update($data);
            if ($company->is_default) {
                Company::where('id', '!=', $company->id)->update(['is_default' => false]);
            }
        });

        return response()->json($company->refresh()->toApi());
    }

    public function branches(Request $request)
    {
        $query = Branch::with(['company', 'warehouse'])->orderBy('name');
        if ($companyId = $request->query('company')) {
            $query->where('company_id', $companyId);
        }

        return response()->json([
            'results' => $query->get()->map(fn (Branch $b) => $b->toApi())->values()->all(),
        ]);
    }

    public function storeBranch(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
        ]);
        foreach (['address', 'city', 'phone'] as $field) {
            $data[$field] = $data[$field] ?? '';
        }

        return response()->json(Branch::create($data)->load('company')->toApi(), 201);
    }

    public function businessUnits(Request $request)
    {
        return response()->json([
            'results' => BusinessUnit::with(['company', 'manager'])->orderBy('name')
                ->get()->map(fn (BusinessUnit $u) => $u->toApi())->values()->all(),
        ]);
    }

    public function storeBusinessUnit(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['sometimes', Rule::in(BusinessUnit::KINDS)],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        return response()->json(BusinessUnit::create($data)->toApi(), 201);
    }

    // ---------------- fiscal years ----------------

    public function fiscalYears(Request $request)
    {
        return response()->json([
            'results' => FiscalYear::with('closer')->orderByDesc('starts_on')
                ->get()->map(fn (FiscalYear $y) => $y->toApi())->values()->all(),
        ]);
    }

    public function storeFiscalYear(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:40'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ]);

        // Overlapping years would make "which period is this?" ambiguous.
        $overlap = FiscalYear::where('company_id', $data['company_id'])
            ->where('starts_on', '<=', $data['ends_on'])
            ->where('ends_on', '>=', $data['starts_on'])
            ->exists();
        if ($overlap) {
            return response()->json(
                ['detail' => 'This period overlaps an existing fiscal year.'],
                422
            );
        }

        return response()->json(FiscalYear::create($data)->toApi(), 201);
    }

    public function updateFiscalYearStatus(Request $request, FiscalYear $fiscalYear)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(FiscalYear::STATUSES)],
        ]);

        $fiscalYear->update([
            'status' => $data['status'],
            'closed_at' => $data['status'] === FiscalYear::OPEN ? null : now(),
            'closed_by' => $data['status'] === FiscalYear::OPEN ? null : $request->user()->id,
        ]);

        return response()->json($fiscalYear->refresh()->toApi());
    }

    // ---------------- numbering ----------------

    public function sequences()
    {
        return response()->json([
            'results' => NumberingSequence::orderBy('key')->get()
                ->map(fn (NumberingSequence $s) => $s->toApi())->values()->all(),
        ]);
    }

    public function updateSequence(Request $request, NumberingSequence $sequence)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'format' => ['sometimes', 'string', 'max:60'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:12'],
            'reset_period' => ['sometimes', Rule::in(['yearly', 'monthly', 'never'])],
            'is_active' => ['sometimes', 'boolean'],
            // Moving the counter backwards would re-issue numbers already used.
            'next_number' => ['sometimes', 'integer', 'min:' . (int) $sequence->next_number],
        ]);

        $sequence->update($data);

        return response()->json($sequence->refresh()->toApi());
    }

    // ---------------- permissions ----------------

    public function permissions()
    {
        return response()->json([
            'results' => Permission::orderBy('module')->orderBy('key')->get()
                ->map(fn (Permission $p) => $p->toApi())->values()->all(),
            'groups' => PermissionGroup::with('permissions')->orderBy('name')->get()
                ->map(fn (PermissionGroup $g) => $g->toApi())->values()->all(),
        ]);
    }

    public function roles()
    {
        return response()->json([
            'results' => Role::with(['parent', 'permissions'])->orderBy('level')->get()
                ->map(fn (Role $r) => $r->toApi(withPermissions: true))->values()->all(),
        ]);
    }

    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:40', 'unique:roles,key'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:roles,id'],
            'level' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);
        $data['description'] ??= '';
        $data['is_system'] = false;

        $role = Role::create($data);
        PermissionAudit::record('role_created', $request->user(), role: $role, detail: $role->key);

        return response()->json($role->toApi(), 201);
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);

        // Locking the admin role out of its own settings screen would make the
        // system unadministrable with no way back.
        if ($role->key === Role::ADMIN && ! in_array('settings.permissions', $data['permissions'], true)) {
            return response()->json([
                'detail' => 'The administrator role must keep settings.permissions, otherwise nobody can undo this.',
            ], 422);
        }

        $role = PermissionService::syncRolePermissions($role, $data['permissions'], $request->user());

        return response()->json($role->load('permissions')->toApi(withPermissions: true));
    }

    public function destroyRole(Request $request, Role $role)
    {
        if ($role->is_system) {
            return response()->json(['detail' => 'Built-in roles cannot be deleted.'], 422);
        }

        PermissionAudit::record('role_deleted', $request->user(), role: $role, detail: $role->key);
        $role->delete();

        return response()->json(null, 204);
    }

    /** Effective permissions of one user, with where each came from. */
    public function userPermissions(User $user)
    {
        return response()->json([
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'effective' => PermissionService::keysFor($user),
            'overrides' => UserPermission::with(['permission', 'grantor'])
                ->where('user_id', $user->id)->get()
                ->map(fn (UserPermission $p) => $p->toApi())->values()->all(),
            'object_rules' => ObjectPermission::where('user_id', $user->id)->get()
                ->map(fn (ObjectPermission $p) => $p->toApi())->values()->all(),
        ]);
    }

    public function grantPermission(Request $request, User $user)
    {
        $data = $request->validate([
            'permission' => ['required', 'string', 'exists:permissions,key'],
            'allow' => ['sometimes', 'boolean'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $grant = PermissionService::grantToUser(
            target: $user,
            permissionKey: $data['permission'],
            actor: $request->user(),
            allow: (bool) ($data['allow'] ?? true),
            expiresAt: $data['expires_at'] ?? null,
            reason: $data['reason'] ?? '',
        );

        return response()->json($grant->load('permission')->toApi(), 201);
    }

    public function revokePermission(Request $request, User $user)
    {
        $data = $request->validate([
            'permission' => ['required', 'string', 'exists:permissions,key'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        PermissionService::revokeFromUser(
            $user, $data['permission'], $request->user(), $data['reason'] ?? ''
        );

        return response()->json(['detail' => 'Override removed.']);
    }

    public function storeFieldPermission(Request $request)
    {
        $data = $request->validate([
            'role_id' => ['sometimes', 'nullable', 'integer', 'exists:roles,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'subject_type' => ['required', 'string', 'max:60'],
            'field' => ['required', 'string', 'max:60'],
            'access' => ['required', Rule::in(FieldPermission::ACCESS)],
        ]);

        if (empty($data['role_id']) && empty($data['user_id'])) {
            return response()->json(['detail' => 'Target a role or a user.'], 422);
        }

        $rule = FieldPermission::create($data);
        PermissionService::flush();

        return response()->json($rule->toApi(), 201);
    }

    public function permissionAudit(Request $request)
    {
        $query = PermissionAudit::with(['actor', 'targetUser', 'role'])->orderByDesc('id');
        if ($userId = $request->query('user')) {
            $query->where('target_user_id', $userId);
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (PermissionAudit $a) => $a->toApi())
        );
    }

    // ---------------- audit trail ----------------

    public function audit(Request $request)
    {
        $query = AuditEntry::with('user')->orderByDesc('id');

        foreach (['event', 'actor', 'batch_id'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }
        if ($type = $request->query('type')) {
            $query->where('auditable_type', $type);
        }
        if ($id = $request->query('id')) {
            $query->where('auditable_id', $id);
        }
        if ($userId = $request->query('user')) {
            $query->where('user_id', $userId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (AuditEntry $a) => $a->toApi())
        );
    }

    /** Activity timeline for one record — used by every entity's detail view. */
    public function timeline(Request $request, string $type, int $id)
    {
        $query = AuditEntry::with('user')
            ->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->orderByDesc('id');

        return response()->json(
            DrfPagination::paginate($query, $request, fn (AuditEntry $a) => $a->toApi())
        );
    }

    public function exportAudit(Request $request)
    {
        $rows = AuditEntry::with('user')->orderByDesc('id')->limit(5000)->get();

        // Exporting the audit trail is itself an audited act.
        AuditService::recordExport('audit_entries', $rows->count(), $request->user());

        $csv = "id,created_at,event,type,record_id,label,user,actor,changed_fields,ip,reason\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                [
                    $row->id,
                    $row->created_at?->toISOString(),
                    $row->event,
                    $row->auditable_type,
                    $row->auditable_id,
                    $row->label,
                    $row->user?->email,
                    $row->actor,
                    implode('|', $row->changed_fields ?? []),
                    $row->ip,
                    $row->reason,
                ]
            )) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit_trail.csv"',
        ]);
    }

    // ---------------- feature flags ----------------

    public function features()
    {
        return response()->json([
            'results' => FeatureFlag::orderBy('name')->get()
                ->map(fn (FeatureFlag $f) => $f->toApi())->values()->all(),
        ]);
    }

    public function updateFeature(Request $request, FeatureFlag $feature)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        if ($feature->is_locked) {
            return response()->json(
                ['detail' => 'This module is required and cannot be switched off.'],
                422
            );
        }

        $feature->update($data);
        FeatureFlag::flush();

        return response()->json($feature->refresh()->toApi());
    }
}
