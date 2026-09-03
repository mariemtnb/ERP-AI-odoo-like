<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding tax (retenue à la source) on supplier payments.
 *
 * When paying a supplier, the company may withhold a percentage and remit it to
 * the state, paying the supplier the net. The payment then posts Dr Payable
 * (gross) / Cr Treasury (net) / Cr Withholding tax payable (the retenue). The
 * withheld amount is stored on the payment for the withholding certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('withholding_amount', 14, 3)->nullable()->after('fx_gain_loss');
        });

        if (! DB::table('accounts')->where('code', '2200')->exists()) {
            DB::table('accounts')->insert([
                'code' => '2200', 'name' => 'Withholding tax payable', 'type' => 'liability',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if (! DB::table('account_mappings')->where('key', 'withholding_payable')->exists()) {
            DB::table('account_mappings')->insert([
                'key' => 'withholding_payable', 'account_code' => '2200',
                'label' => 'Withholding tax payable', 'description' => 'Retenues à la source à reverser',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! DB::table('feature_flags')->where('key', 'withholding')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'withholding', 'name' => 'Withholding tax',
                'description' => 'Withhold retenue à la source on supplier payments',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('withholding_amount');
        });
        DB::table('account_mappings')->where('key', 'withholding_payable')->delete();
        DB::table('accounts')->where('code', '2200')->delete();
        DB::table('feature_flags')->where('key', 'withholding')->delete();
    }
};
