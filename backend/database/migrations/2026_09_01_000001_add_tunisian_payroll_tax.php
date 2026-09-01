<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tunisian payroll: statutory social security (CNSS) and income tax (IRPP/CSS).
 *
 * Payslips gain the computed statutory amounts; employees gain the family
 * situation that drives the IRPP relief; and a single-row settings table holds
 * every rate and bracket so nothing is hardcoded and the figures can be updated
 * when the finance law changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drives the IRPP family relief.
            $table->boolean('head_of_family')->default(false)->after('base_salary');
            $table->unsignedTinyInteger('dependent_children')->default(0)->after('head_of_family');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('cnss_employee', 12, 3)->default(0)->after('gross_pay'); // withheld from pay
            $table->decimal('cnss_employer', 12, 3)->default(0)->after('cnss_employee'); // employer cost, not withheld
            $table->decimal('taxable_base', 12, 3)->default(0)->after('cnss_employer'); // monthly IRPP base
            $table->decimal('irpp', 12, 3)->default(0)->after('taxable_base');           // income tax withheld
            $table->decimal('css', 12, 3)->default(0)->after('irpp');                    // solidarity contribution
        });

        // One row of configuration. Every rate is editable in Administration.
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('cnss_employee_rate', 6, 4)->default(0.0918); // 9.18 %
            $table->decimal('cnss_employer_rate', 6, 4)->default(0.1657); // 16.57 %
            $table->decimal('css_rate', 6, 4)->default(0.0050);           // 0.5 %
            $table->decimal('expense_abatement_rate', 6, 4)->default(0.1000); // 10 % professional expenses
            $table->decimal('expense_abatement_cap', 12, 3)->default(2000); // annual cap on that abatement
            $table->decimal('head_of_family_deduction', 12, 3)->default(300); // annual
            $table->decimal('child_deduction', 12, 3)->default(100);        // annual, per child
            $table->unsignedTinyInteger('max_children')->default(4);
            // Progressive annual scale: [{ "upto": 5000, "rate": 0 }, …, { "upto": null, "rate": 0.40 }]
            $table->json('irpp_brackets');
            $table->timestamps();
        });

        DB::table('payroll_settings')->insert([
            'irpp_brackets' => json_encode([
                ['upto' => 5000,  'rate' => 0.00],
                ['upto' => 10000, 'rate' => 0.15],
                ['upto' => 20000, 'rate' => 0.25],
                ['upto' => 30000, 'rate' => 0.30],
                ['upto' => 40000, 'rate' => 0.33],
                ['upto' => 50000, 'rate' => 0.36],
                ['upto' => 70000, 'rate' => 0.38],
                ['upto' => null,  'rate' => 0.40],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ledger accounts so the statutory withholdings post to their own
        // liabilities (Tunisian PCG 431x / 432x), editable like the rest.
        foreach ([
            ['key' => 'cnss_payable', 'code' => '4311', 'name' => 'CNSS à payer', 'label' => 'CNSS payable'],
            ['key' => 'income_tax_payable', 'code' => '4321', 'name' => 'Retenues IRPP / CSS', 'label' => 'Income tax & CSS payable'],
        ] as $m) {
            if (! DB::table('accounts')->where('code', $m['code'])->exists()) {
                DB::table('accounts')->insert([
                    'code' => $m['code'], 'name' => $m['name'], 'type' => 'liability',
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if (! DB::table('account_mappings')->where('key', $m['key'])->exists()) {
                DB::table('account_mappings')->insert([
                    'key' => $m['key'], 'account_code' => $m['code'], 'label' => $m['label'],
                    'description' => 'Payroll statutory withholding', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['head_of_family', 'dependent_children']);
        });
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['cnss_employee', 'cnss_employer', 'taxable_base', 'irpp', 'css']);
        });
        Schema::dropIfExists('payroll_settings');
        DB::table('account_mappings')->whereIn('key', ['cnss_payable', 'income_tax_payable'])->delete();
    }
};
