<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reordering rules / auto-replenishment. Each rule sets a reorder point
 * (min_qty) and an order quantity (reorder_qty) for a product, with a preferred
 * supplier. When on-hand stock falls to or below the reorder point the product
 * is suggested for replenishment, and draft purchase orders can be generated in
 * one action, grouped by supplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reorder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('min_qty', 12, 3)->default(0);      // reorder point
            $table->decimal('reorder_qty', 12, 3)->default(0);  // quantity to order
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reorder_rules');
    }
};
