<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 administration foundation: multi-company structure, an enterprise
 * permission engine, a full audit trail and feature flags.
 *
 * Additive by construction. The existing `role` column on users stays the
 * source of truth for the built-in roles, and the `role:` middleware keeps
 * working — it now resolves through the permission engine, so no existing
 * route or test changes behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ---------------------------------------------------------------
        // Organisation structure
        // ---------------------------------------------------------------
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 200);
            $table->string('legal_name', 200)->default('');
            // Links to the fiscal profile added by the localization layer.
            $table->foreignId('company_profile_id')->nullable()
                ->constrained('company_profiles')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('companies')->nullOnDelete();
            $table->string('currency', 3)->default('TND');
            $table->string('locale', 5)->default('fr');
            $table->string('timezone', 64)->default('Africa/Tunis');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('parent_id');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->text('address')->default('');
            $table->string('city', 100)->default('');
            $table->string('phone', 30)->default('');
            // A branch usually maps onto a physical warehouse.
            $table->foreignId('warehouse_id')->nullable()
                ->constrained('warehouses')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            // Cost/profit centre used to tag entries for analytical reporting.
            $table->string('kind', 20)->default('cost_centre'); // cost_centre|profit_centre|division
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        // ---------------------------------------------------------------
        // Fiscal years & periods — closing a period must block backdating.
        // ---------------------------------------------------------------
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 40);            // "2026"
            $table->date('starts_on');
            $table->date('ends_on');
            // open|closed|locked — locked means not even an admin may post.
            $table->string('status', 12)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'starts_on', 'ends_on']);
        });

        // ---------------------------------------------------------------
        // Numbering sequences — replaces the count()-based numbering, which
        // is racy and renumbers if a record is ever deleted.
        // ---------------------------------------------------------------
        Schema::create('numbering_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('key', 40);             // sale|purchase|invoice|journal_entry|cheque…
            $table->string('name', 100)->default('');
            $table->string('format', 60)->default('{PREFIX}-{YYYY}-{SEQ:4}');
            $table->string('prefix', 12)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            // yearly|monthly|never — when the counter resets.
            $table->string('reset_period', 10)->default('yearly');
            $table->string('current_period', 10)->default('');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'key']);
        });

        // ---------------------------------------------------------------
        // Permission engine
        // ---------------------------------------------------------------
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            // Dotted and hierarchical: "accounting.entries.create".
            $table->string('key', 80)->unique();
            $table->string('name', 150);
            $table->string('module', 40)->default('core');
            $table->string('description', 255)->default('');
            // Marks permissions that authorise approving someone else's work.
            $table->boolean('is_approval')->default(false);
            $table->timestamps();
            $table->index('module');
        });

        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->default('');
            $table->timestamps();
        });

        Schema::create('permission_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_group_id')->constrained('permission_groups')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->unique(['permission_group_id', 'permission_id'], 'pgi_unique');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->default('');
            // Roles inherit: manager extends employee, admin extends manager.
            $table->foreignId('parent_id')->nullable()->constrained('roles')->nullOnDelete();
            // Built-ins (admin/manager/employee) cannot be deleted — the
            // users.role column and the `role:` middleware depend on them.
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('level')->default(0);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('permission_group_id')->nullable()->constrained('permission_groups')->cascadeOnDelete();
            // deny beats allow, so an inherited grant can be revoked.
            $table->boolean('allow')->default(true);
            $table->timestamps();
            $table->index(['role_id', 'allow']);
        });

        // Per-user overrides, optionally time-boxed (temporary permissions:
        // "give Amine approval rights while the manager is on leave").
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('allow')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('reason', 255)->default('');
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
        });

        // Extra roles beyond users.role, so a user can hold custom roles too.
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'company_id'], 'user_roles_unique');
        });

        // Object-level rules: "this user may only read sales of branch 3".
        Schema::create('object_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->string('subject_type', 60);    // sale|purchase|customer|…
            $table->unsignedBigInteger('subject_id')->nullable();  // null = all of that type
            $table->string('ability', 40);         // view|update|delete|approve
            $table->boolean('allow')->default(true);
            // Optional scope narrowing instead of a single id.
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'role_id']);
        });

        // Field-level visibility: hide cost_price from employees, etc.
        Schema::create('field_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 60);
            $table->string('field', 60);
            // hidden|readonly|visible — visible is an explicit re-grant.
            $table->string('access', 10)->default('hidden');
            $table->timestamps();
            $table->index(['subject_type', 'field']);
        });

        // Immutable log of permission changes — who granted what, to whom, why.
        Schema::create('permission_audits', function (Blueprint $table) {
            $table->id();
            $table->string('action', 40);          // grant|revoke|role_assigned|role_created…
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('permission_key', 80)->default('');
            $table->text('detail')->default('');
            $table->string('reason', 255)->default('');
            $table->string('ip', 45)->default('');
            $table->timestamp('created_at')->useCurrent();
            $table->index('target_user_id');
            $table->index('created_at');
        });

        // ---------------------------------------------------------------
        // Audit trail
        // ---------------------------------------------------------------
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->id();
            $table->string('event', 30);           // created|updated|deleted|viewed|exported…
            $table->string('auditable_type', 80);
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('label', 200)->default('');   // human name at the time

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // user|agent|system — the AI acts on a user's behalf but is not them.
            $table->string('actor', 10)->default('user');

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();

            $table->string('reason', 255)->default('');
            $table->string('ip', 45)->default('');
            $table->string('user_agent', 255)->default('');
            $table->string('url', 255)->default('');
            $table->string('method', 10)->default('');
            // Groups the rows written by one bulk operation.
            $table->string('batch_id', 40)->default('');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('batch_id');
            $table->index('created_at');
        });

        // ---------------------------------------------------------------
        // Feature flags
        // ---------------------------------------------------------------
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->default('');
            $table->boolean('enabled')->default(true);
            // Flags a deployment must never switch off (auth, core).
            $table->boolean('is_locked')->default(false);
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->timestamps();
        });

        $this->seedDefaults($now);
    }

    /** Baseline data the engine needs to behave exactly like today's RBAC. */
    private function seedDefaults($now): void
    {
        // --- default company, derived from the localization profile ---
        $profile = DB::table('company_profiles')->orderBy('id')->first();
        $companyId = DB::table('companies')->insertGetId([
            'code' => 'MAIN',
            'name' => $profile->legal_name ?? 'Main company',
            'legal_name' => $profile->legal_name ?? '',
            'company_profile_id' => $profile->id ?? null,
            'currency' => $profile->currency ?? 'TND',
            'locale' => $profile->locale ?? 'fr',
            'timezone' => 'Africa/Tunis',
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $warehouseId = DB::table('warehouses')->where('is_default', true)->value('id');
        DB::table('branches')->insert([
            'company_id' => $companyId,
            'code' => 'HQ',
            'name' => 'Head office',
            'warehouse_id' => $warehouseId,
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // --- fiscal year covering today, so postings are never orphaned ---
        $startMonth = (int) ($profile->fiscal_year_start_month ?? 1);
        $year = (int) now()->year;
        $start = sprintf('%04d-%02d-01', $year, $startMonth);
        $end = date('Y-m-d', strtotime($start . ' +1 year -1 day'));
        DB::table('fiscal_years')->insert([
            'company_id' => $companyId,
            'name' => (string) $year,
            'starts_on' => $start,
            'ends_on' => $end,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // --- numbering sequences matching today's prefixes exactly ---
        $sequences = [
            ['key' => 'sale', 'prefix' => 'SO', 'name' => 'Sales orders'],
            ['key' => 'purchase', 'prefix' => 'PO', 'name' => 'Purchase orders'],
            ['key' => 'invoice', 'prefix' => 'INV', 'name' => 'Invoices'],
            ['key' => 'journal_entry', 'prefix' => 'JE', 'name' => 'Journal entries'],
            ['key' => 'cheque', 'prefix' => 'CHQ', 'name' => 'Cheques'],
            ['key' => 'traite', 'prefix' => 'EFF', 'name' => 'Commercial paper'],
            ['key' => 'payment', 'prefix' => 'PAY', 'name' => 'Payments'],
            ['key' => 'installment_plan', 'prefix' => 'PLAN', 'name' => 'Installment plans'],
        ];
        DB::table('numbering_sequences')->insert(array_map(fn ($s) => $s + [
            'company_id' => $companyId,
            'format' => '{PREFIX}-{YYYY}-{SEQ:4}',
            'next_number' => 1,
            'reset_period' => 'yearly',
            'current_period' => (string) $year,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $sequences));

        // --- feature flags, one per module ---
        $modules = [
            ['key' => 'accounting', 'name' => 'Accounting', 'enabled' => true],
            ['key' => 'crm', 'name' => 'CRM', 'enabled' => true],
            ['key' => 'inventory', 'name' => 'Inventory', 'enabled' => true],
            ['key' => 'purchasing', 'name' => 'Purchasing', 'enabled' => true],
            ['key' => 'sales', 'name' => 'Sales', 'enabled' => true],
            ['key' => 'localization', 'name' => 'Tunisia localization', 'enabled' => true],
            ['key' => 'treasury', 'name' => 'Treasury (cheques, effets, instalments)', 'enabled' => true],
            ['key' => 'banking', 'name' => 'Banking & reconciliation', 'enabled' => true],
            ['key' => 'ai', 'name' => 'AI assistant', 'enabled' => true],
            ['key' => 'documents', 'name' => 'Documents & RAG', 'enabled' => true],
            ['key' => 'reports', 'name' => 'Reports', 'enabled' => true],
            ['key' => 'pos', 'name' => 'Point of sale', 'enabled' => false],
            ['key' => 'hr', 'name' => 'Human resources', 'enabled' => false],
            ['key' => 'manufacturing', 'name' => 'Manufacturing', 'enabled' => false],
            // OFF by design: an employee account reads the entire ERP, so open
            // sign-up would expose a company's whole book of business to
            // anyone who can reach the login page.
            ['key' => 'self_registration', 'name' => 'Public self-registration', 'enabled' => false],
        ];
        DB::table('feature_flags')->insert(array_map(fn ($m) => $m + [
            'description' => '',
            'is_locked' => false,
            'company_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $modules));

        // --- permissions ---
        $permissions = [];
        $crud = ['view' => 'View', 'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete'];
        $modulesWithCrud = [
            'products' => 'inventory', 'categories' => 'inventory', 'stock' => 'inventory',
            'warehouses' => 'inventory', 'customers' => 'sales', 'suppliers' => 'purchasing',
            'sales' => 'sales', 'purchases' => 'purchasing', 'leads' => 'crm',
            'accounting' => 'accounting', 'instruments' => 'treasury',
            'installments' => 'treasury', 'payments' => 'treasury',
            'banks' => 'banking', 'reconciliation' => 'banking',
            'documents' => 'documents', 'users' => 'core', 'settings' => 'core',
        ];
        foreach ($modulesWithCrud as $subject => $module) {
            foreach ($crud as $ability => $verb) {
                $permissions[] = [
                    'key' => "{$subject}.{$ability}",
                    'name' => "{$verb} {$subject}",
                    'module' => $module,
                    'description' => '',
                    'is_approval' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        // Approval and action permissions that are not plain CRUD.
        foreach ([
            ['purchases.approve', 'Approve purchase orders', 'purchasing', true],
            ['sales.confirm', 'Confirm sales', 'sales', false],
            ['instruments.clear', 'Clear cheques and effets', 'treasury', true],
            ['instruments.bounce', 'Record bounced instruments', 'treasury', true],
            ['reconciliation.match', 'Match bank transactions', 'banking', true],
            ['accounting.post', 'Post manual journal entries', 'accounting', false],
            ['reports.export', 'Export reports', 'reports', false],
            ['settings.permissions', 'Manage roles and permissions', 'core', false],
            ['settings.audit', 'Read the audit trail', 'core', false],
            ['ai.use', 'Use the AI assistant', 'ai', false],
            ['ai.write', 'Let the AI perform write actions', 'ai', true],
        ] as [$key, $name, $module, $isApproval]) {
            $permissions[] = [
                'key' => $key, 'name' => $name, 'module' => $module,
                'description' => '', 'is_approval' => $isApproval,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('permissions')->insert($permissions);

        // --- built-in roles, mirroring today's RBAC matrix exactly ---
        $employeeId = DB::table('roles')->insertGetId([
            'key' => 'employee', 'name' => 'Employee', 'is_system' => true, 'level' => 10,
            'description' => 'Day-to-day data entry within permissions.',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $managerId = DB::table('roles')->insertGetId([
            'key' => 'manager', 'name' => 'Manager', 'parent_id' => $employeeId,
            'is_system' => true, 'level' => 20,
            'description' => 'Operations: catalog, stock, purchases, sales, treasury, reports.',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $adminId = DB::table('roles')->insertGetId([
            'key' => 'admin', 'name' => 'Administrator', 'parent_id' => $managerId,
            'is_system' => true, 'level' => 30,
            'description' => 'Full control, including users and settings.',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $idFor = DB::table('permissions')->pluck('id', 'key');
        $grant = function (int $roleId, array $keys) use ($idFor, $now) {
            $rows = [];
            foreach ($keys as $key) {
                if (isset($idFor[$key])) {
                    $rows[] = [
                        'role_id' => $roleId, 'permission_id' => $idFor[$key],
                        'allow' => true, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            if ($rows) {
                DB::table('role_permissions')->insert($rows);
            }
        };

        // Employee: reads everywhere, plus creating customers and sales.
        $employeeKeys = ['ai.use'];
        foreach (array_keys($modulesWithCrud) as $subject) {
            if (! in_array($subject, ['users', 'settings'], true)) {
                $employeeKeys[] = "{$subject}.view";
            }
        }
        $employeeKeys[] = 'customers.create';
        $employeeKeys[] = 'sales.create';
        $employeeKeys[] = 'sales.confirm';
        $employeeKeys[] = 'leads.create';
        $employeeKeys[] = 'leads.update';
        $grant($employeeId, $employeeKeys);

        // Manager: everything operational except users and settings.
        $managerKeys = ['reports.export', 'accounting.post', 'ai.write',
            'purchases.approve', 'instruments.clear', 'instruments.bounce',
            'reconciliation.match'];
        foreach ($modulesWithCrud as $subject => $module) {
            if (in_array($subject, ['users', 'settings'], true)) {
                continue;
            }
            foreach (array_keys($crud) as $ability) {
                $managerKeys[] = "{$subject}.{$ability}";
            }
        }
        // Approving one's own large purchase order stays an admin act.
        $managerKeys = array_diff($managerKeys, ['purchases.approve']);
        $grant($managerId, $managerKeys);

        // Admin: literally everything.
        $grant($adminId, $idFor->keys()->all());

        // --- permission groups, for assigning sets at a time ---
        $groups = [
            ['key' => 'treasury_officer', 'name' => 'Treasury officer',
                'perms' => ['instruments.view', 'instruments.create', 'instruments.update',
                    'instruments.clear', 'instruments.bounce', 'payments.view',
                    'payments.create', 'reconciliation.match', 'reconciliation.view']],
            ['key' => 'accountant', 'name' => 'Accountant',
                'perms' => ['accounting.view', 'accounting.create', 'accounting.post',
                    'reports.export', 'settings.audit']],
            ['key' => 'read_only', 'name' => 'Read only',
                'perms' => array_values(array_map(
                    fn ($s) => "{$s}.view",
                    array_diff(array_keys($modulesWithCrud), ['users', 'settings'])
                ))],
        ];
        foreach ($groups as $group) {
            $groupId = DB::table('permission_groups')->insertGetId([
                'key' => $group['key'], 'name' => $group['name'], 'description' => '',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $rows = [];
            foreach ($group['perms'] as $key) {
                if (isset($idFor[$key])) {
                    $rows[] = ['permission_group_id' => $groupId, 'permission_id' => $idFor[$key]];
                }
            }
            if ($rows) {
                DB::table('permission_group_items')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('audit_entries');
        Schema::dropIfExists('permission_audits');
        Schema::dropIfExists('field_permissions');
        Schema::dropIfExists('object_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permission_group_items');
        Schema::dropIfExists('permission_groups');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('numbering_sequences');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('business_units');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
