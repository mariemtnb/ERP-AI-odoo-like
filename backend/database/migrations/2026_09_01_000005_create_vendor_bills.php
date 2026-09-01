<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor bills with 3-way matching.
 *
 * A supplier's invoice is recorded and checked against the purchase order it
 * belongs to and what was actually received: quantities and prices must agree.
 * A bill that matches is cleared for payment; anything else (over-billing, a
 * price that drifted, goods not yet received, or no PO at all) is flagged as an
 * exception that a manager must approve before it can be paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->date('bill_date');
            $table->string('supplier_ref', 120)->default('');   // the supplier's own invoice number
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 12)->default('draft');      // matched|exception|approved|paid
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('vendor_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_bill_id')->constrained('vendor_bills')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
        });

        if (! DB::table('feature_flags')->where('key', 'vendor_bills')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'vendor_bills', 'name' => 'Vendor bills (3-way match)',
                'description' => 'Match supplier invoices to purchase orders and receipts',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bill_lines');
        Schema::dropIfExists('vendor_bills');
        DB::table('feature_flags')->where('key', 'vendor_bills')->delete();
    }
};
