<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foreign-currency settlements with realized FX gain/loss.
 *
 * A payment may now settle a foreign-currency receivable/payable: it records
 * the foreign amount, the rate the debt was booked at, and the rate on the
 * settlement date. The base value that hits the treasury and the base value
 * that relieves receivable/payable differ, and the gap is posted to a realized
 * FX gain or loss account. Base-currency payments are unaffected — every new
 * column is nullable and left null for them.
 *
 * Tunisian chart: 736/636 «gains/pertes de change» sit in the localized chart;
 * this simplified chart uses 7600 / 6600, mapped through account_mappings so a
 * deployment on the full PCG re-points them without code changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('amount');   // foreign currency; null = base
            $table->decimal('foreign_amount', 14, 2)->nullable()->after('currency_code');
            $table->decimal('book_rate', 18, 8)->nullable()->after('foreign_amount');       // rate the debt was booked at
            $table->decimal('settlement_rate', 18, 8)->nullable()->after('book_rate');       // rate on the settlement date
            $table->decimal('fx_gain_loss', 14, 3)->nullable()->after('settlement_rate');    // realized, base; + gain / − loss
        });

        $now = now();
        foreach ([
            ['code' => '7600', 'name' => 'Foreign exchange gain', 'type' => 'income'],
            ['code' => '6600', 'name' => 'Foreign exchange loss', 'type' => 'expense'],
        ] as $account) {
            if (! DB::table('accounts')->where('code', $account['code'])->exists()) {
                DB::table('accounts')->insert($account + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach ([
            ['key' => 'fx_gain', 'account_code' => '7600', 'label' => 'FX gain', 'description' => 'Gains de change réalisés'],
            ['key' => 'fx_loss', 'account_code' => '6600', 'label' => 'FX loss', 'description' => 'Pertes de change réalisées'],
        ] as $map) {
            if (! DB::table('account_mappings')->where('key', $map['key'])->exists()) {
                DB::table('account_mappings')->insert($map + ['created_at' => $now, 'updated_at' => $now]);
            }
        }

        if (! DB::table('feature_flags')->where('key', 'foreign_currency')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'foreign_currency', 'name' => 'Foreign-currency settlements',
                'description' => 'Settle foreign-currency invoices with realized FX gain/loss',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'foreign_amount', 'book_rate', 'settlement_rate', 'fx_gain_loss']);
        });
        DB::table('account_mappings')->whereIn('key', ['fx_gain', 'fx_loss'])->delete();
        DB::table('accounts')->whereIn('code', ['7600', '6600'])->delete();
        DB::table('feature_flags')->where('key', 'foreign_currency')->delete();
    }
};
