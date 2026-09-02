<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Landed costs on goods receipts.
 *
 * Freight, duty and insurance incurred on a received purchase order are spread
 * across its received lines (by value or by quantity) and capitalised into
 * inventory: each affected product's average cost rises, and the ledger posts
 * Dr Inventory / Cr the landed-cost payable. So the Inventory account and the
 * AVCO unit cost reflect what the goods actually cost to land, not just their
 * supplier price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landed_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 14, 3);
            $table->string('allocation', 10)->default('value');   // value | quantity
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('landed_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landed_cost_id')->constrained('landed_costs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('basis', 14, 3);     // the value or quantity this share was weighted by
            $table->decimal('amount', 14, 3);    // the cost allocated to this product
            $table->timestamps();
        });

        // Payable owed for the landed cost. Defaults to Accounts payable; a
        // deployment can re-point it (e.g. a freight-payable account).
        if (! DB::table('account_mappings')->where('key', 'landed_costs')->exists()) {
            $payable = DB::table('account_mappings')->where('key', 'payable')->value('account_code') ?? '2000';
            DB::table('account_mappings')->insert([
                'key' => 'landed_costs', 'account_code' => $payable,
                'label' => 'Landed cost payable', 'description' => 'Freight / duty / insurance owed on receipts',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! DB::table('feature_flags')->where('key', 'landed_costs')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'landed_costs', 'name' => 'Landed costs',
                'description' => 'Capitalise freight and duty into inventory value',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_cost_allocations');
        Schema::dropIfExists('landed_costs');
        DB::table('account_mappings')->where('key', 'landed_costs')->delete();
        DB::table('feature_flags')->where('key', 'landed_costs')->delete();
    }
};
