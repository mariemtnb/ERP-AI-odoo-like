<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot / batch tracking with expiry, for perishable goods. A lot is a quantity
 * of one product received together with one expiry date. Receiving a lot also
 * records a normal stock movement, so the aggregate on-hand figure the rest of
 * the system uses stays correct; lots add the batch/expiry dimension on top and
 * are consumed first-expired-first-out (FEFO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('lot_number');
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 3)->default(0); // remaining in this lot
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
            $table->index(['product_id', 'warehouse_id', 'expiry_date']);
            $table->unique(['product_id', 'warehouse_id', 'lot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
