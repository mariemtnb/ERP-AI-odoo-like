<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partial goods receipts.
 *
 * A purchase order can now be received in instalments: each line tracks how
 * much has arrived so far, the order sits at "partial" until everything is in,
 * and only then becomes "received". Vendor-bill matching reads the same
 * received quantity, so a bill is checked against what actually arrived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('received_qty', 12, 3)->default(0)->after('quantity');
        });

        // Existing fully-received orders: mark their lines fully received so the
        // "remaining" maths and vendor-bill matching stay correct.
        DB::table('purchase_order_lines')
            ->whereIn('purchase_order_id', function ($q) {
                $q->select('id')->from('purchase_orders')->where('status', 'received');
            })
            ->update(['received_qty' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn('received_qty');
        });
    }
};
