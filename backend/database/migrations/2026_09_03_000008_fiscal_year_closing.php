<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal-year closing entries.
 *
 * Closing a year posts a closing journal (OD / opérations diverses) that zeroes
 * every income and expense account and rolls the net result into retained
 * earnings, so the new year starts with a clean profit-and-loss and the result
 * sits in equity. The closing entry is linked back to the fiscal year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreignId('closing_entry_id')->nullable()->after('closed_by')
                ->constrained('journal_entries')->nullOnDelete();
        });

        if (! DB::table('accounts')->where('code', '3100')->exists()) {
            DB::table('accounts')->insert([
                'code' => '3100', 'name' => 'Retained earnings', 'type' => 'equity',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if (! DB::table('account_mappings')->where('key', 'retained_earnings')->exists()) {
            DB::table('account_mappings')->insert([
                'key' => 'retained_earnings', 'account_code' => '3100',
                'label' => 'Retained earnings', 'description' => 'Résultats reportés',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closing_entry_id');
        });
        DB::table('account_mappings')->where('key', 'retained_earnings')->delete();
        DB::table('accounts')->where('code', '3100')->delete();
    }
};
