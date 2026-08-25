<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll ("gestion de paie"), employee advances and bonuses.
 *
 * Same principle as the rest of the Tunisian layer: no legal rule is baked in.
 * Payroll here computes net = base + bonuses − advances recovered − other
 * deductions, and every account it posts to is resolved through the
 * configurable account mapping. Social charges and income-tax scales are NOT
 * hardcoded — a company records them as ordinary deduction lines whose rates
 * its accountant sets. System behaviour and legal validation stay separate.
 *
 * (Owner profitability adds no tables — it reads the existing ledger and sales.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ----- accounts these postings need (Tunisian PCG, editable) -----
        DB::table('accounts')->insert(array_map(
            fn ($a) => $a + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            [
                ['code' => '640', 'name' => 'Charges de personnel', 'type' => 'expense'],
                ['code' => '425', 'name' => 'Personnel - avances et acomptes', 'type' => 'asset'],
                ['code' => '4253', 'name' => 'Personnel - rémunérations dues', 'type' => 'liability'],
                ['code' => '4270', 'name' => 'Autres retenues sur salaires', 'type' => 'liability'],
            ]
        ));

        DB::table('account_mappings')->insert(array_map(
            fn ($m) => $m + ['created_at' => $now, 'updated_at' => $now],
            [
                ['key' => 'salary_expense', 'account_code' => '640', 'label' => 'Salary expense', 'description' => 'Charges de personnel (brut)'],
                ['key' => 'salaries_payable', 'account_code' => '4253', 'label' => 'Salaries payable', 'description' => 'Net à payer aux salariés'],
                ['key' => 'employee_advances', 'account_code' => '425', 'label' => 'Employee advances', 'description' => 'Avances sur salaire'],
                ['key' => 'payroll_deductions', 'account_code' => '4270', 'label' => 'Payroll deductions', 'description' => 'Retenues (hors avances) — cotisations, impôt…'],
            ]
        ));

        // ----- a dedicated payroll journal (PA) -----
        DB::table('journals')->insert([
            'code' => 'PA', 'name' => 'Payroll', 'name_fr' => 'Journal de paie',
            'type' => 'payroll', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // ----- numbering for the new documents -----
        $companyId = DB::table('companies')->where('is_default', true)->value('id')
            ?? DB::table('companies')->min('id');
        if ($companyId) {
            DB::table('numbering_sequences')->insert(array_map(fn ($s) => $s + [
                'company_id' => $companyId, 'format' => '{PREFIX}-{YYYY}-{SEQ:4}',
                'next_number' => 1, 'reset_period' => 'yearly',
                'current_period' => (string) now()->year, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ], [
                ['key' => 'employee', 'prefix' => 'EMP', 'name' => 'Employees'],
                ['key' => 'payroll_run', 'prefix' => 'PR', 'name' => 'Payroll runs'],
                ['key' => 'employee_advance', 'prefix' => 'ADV', 'name' => 'Employee advances'],
            ]));
        }

        // ----- feature flag for the whole payroll area -----
        DB::table('feature_flags')->insert([
            'key' => 'payroll', 'name' => 'Payroll & HR', 'description' => '',
            'enabled' => true, 'is_locked' => false, 'company_id' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // ----- permissions for the new module -----
        $perms = [];
        foreach (['view' => 'View', 'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete'] as $ability => $verb) {
            $perms[] = ['key' => "payroll.{$ability}", 'name' => "{$verb} payroll", 'module' => 'payroll'];
        }
        $perms[] = ['key' => 'payroll.run', 'name' => 'Approve and pay payroll runs', 'module' => 'payroll'];
        $perms[] = ['key' => 'payroll.advance', 'name' => 'Approve employee advances', 'module' => 'payroll'];
        $perms[] = ['key' => 'reports.profit', 'name' => "View the owner's profit report", 'module' => 'reports'];
        DB::table('permissions')->insert(array_map(fn ($p) => $p + [
            'description' => '', 'is_approval' => str_contains($p['key'], 'run') || str_contains($p['key'], 'advance'),
            'created_at' => $now, 'updated_at' => $now,
        ], $perms));

        // Grant them to the built-in roles: admin everything, manager the lot
        // too (they run operations), employee read-only on the profit report off.
        $permIds = DB::table('permissions')->whereIn('key', array_column($perms, 'key'))->pluck('id', 'key');
        foreach (['admin', 'manager'] as $roleKey) {
            $roleId = DB::table('roles')->where('key', $roleKey)->value('id');
            if (! $roleId) {
                continue;
            }
            DB::table('role_permissions')->insert($permIds->map(fn ($id) => [
                'role_id' => $roleId, 'permission_id' => $id, 'allow' => true,
                'created_at' => $now, 'updated_at' => $now,
            ])->values()->all());
        }

        // ----- employees -----
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120)->default('');
            $table->string('job_title', 120)->default('');
            $table->string('department', 120)->default('');
            $table->decimal('base_salary', 12, 3)->default(0);
            $table->string('currency', 3)->default('TND');
            $table->date('hire_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('phone', 30)->default('');
            $table->string('email', 150)->default('');
            $table->string('rib', 30)->default('');          // for salary transfer
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->default('');
            $table->timestamps();
            $table->index('is_active');
        });

        // ----- employee advances (avance sur salaire) -----
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('amount', 12, 3);
            $table->date('request_date');
            $table->string('reason', 255)->default('');       // sickness, family matter…
            $table->string('method', 20)->default('cash');    // cash|bank_transfer
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            // pending → approved(paid) → recovered ; or cancelled
            $table->string('status', 16)->default('pending');
            $table->date('paid_at')->nullable();
            // The amount already taken back out of payslips.
            $table->decimal('recovered_amount', 12, 3)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        // ----- payroll runs (one per period) -----
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('period_month');                     // first day of the month
            $table->string('label', 120)->default('');
            $table->string('status', 12)->default('draft');   // draft|approved|paid
            $table->decimal('gross_total', 14, 3)->default(0);
            $table->decimal('net_total', 14, 3)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->default('');
            $table->timestamps();
            $table->index('status');
        });

        // ----- payslips (one per employee per run) -----
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->decimal('base_salary', 12, 3)->default(0);
            $table->decimal('earnings_total', 12, 3)->default(0);   // bonuses / primes
            $table->decimal('deductions_total', 12, 3)->default(0); // non-advance deductions
            $table->decimal('advance_recovered', 12, 3)->default(0);
            $table->decimal('gross_pay', 12, 3)->default(0);        // base + earnings
            $table->decimal('net_pay', 12, 3)->default(0);
            $table->string('status', 12)->default('draft');
            $table->text('notes')->default('');
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id'], 'payslip_unique');
        });

        // ----- payslip lines (bonuses and deductions) -----
        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->string('type', 10);          // earning|deduction
            $table->string('label', 150);
            $table->decimal('amount', 12, 3);
            $table->boolean('is_bonus')->default(false);   // a prime
            // If a deduction recovers an advance, it points at it.
            $table->foreignId('employee_advance_id')->nullable()->constrained('employee_advances')->nullOnDelete();
            $table->timestamps();
            $table->index('payslip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_advances');
        Schema::dropIfExists('employees');

        DB::table('permissions')->where('module', 'payroll')->orWhere('key', 'reports.profit')->delete();
        DB::table('feature_flags')->where('key', 'payroll')->delete();
        DB::table('journals')->where('code', 'PA')->delete();
        DB::table('numbering_sequences')->whereIn('key', ['employee', 'payroll_run', 'employee_advance'])->delete();
        DB::table('account_mappings')->whereIn('key', [
            'salary_expense', 'salaries_payable', 'employee_advances', 'payroll_deductions',
        ])->delete();
        DB::table('accounts')->whereIn('code', ['640', '425', '4253', '4270'])->delete();
    }
};
