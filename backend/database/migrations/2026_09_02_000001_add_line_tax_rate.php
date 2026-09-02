<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Line-level VAT.
 *
 * Each sale and purchase line carries its own VAT rate, so a document can mix
 * Tunisian rates (0 / 7 / 13 / 19 %). Prices stay VAT-inclusive — the line's
 * total is unchanged — but the VAT *within* each line is now known per rate,
 * which is what a correct VAT return needs. Existing lines are seeded with the
 * company's default rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $default = (float) (DB::table('company_profiles')->value('default_vat_rate') ?? 19);

        foreach (['sale_lines', 'purchase_order_lines'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('tax_rate', 5, 2)->default(0)->after('unit_price');
            });
            DB::table($table)->update(['tax_rate' => $default]);
        }
    }

    public function down(): void
    {
        Schema::table('sale_lines', fn (Blueprint $t) => $t->dropColumn('tax_rate'));
        Schema::table('purchase_order_lines', fn (Blueprint $t) => $t->dropColumn('tax_rate'));
    }
};
