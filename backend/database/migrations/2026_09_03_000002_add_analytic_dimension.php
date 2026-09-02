<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Analytic dimension on the ledger.
 *
 * A journal-entry line may carry a business unit (cost / profit centre), so
 * income and expense can be reported per dimension alongside the account view.
 * The tag is optional — untagged lines report as "unallocated" — and additive:
 * existing postings are unaffected until a line is assigned a unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->foreignId('business_unit_id')->nullable()->after('account_id')
                ->constrained('business_units')->nullOnDelete();
            $table->index('business_unit_id');
        });

        if (! DB::table('feature_flags')->where('key', 'analytic')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'analytic', 'name' => 'Analytic accounting',
                'description' => 'Tag ledger lines with a cost centre and report P&L per dimension',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
        });
        DB::table('feature_flags')->where('key', 'analytic')->delete();
    }
};
