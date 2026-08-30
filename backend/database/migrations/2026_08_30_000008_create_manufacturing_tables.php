<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Light manufacturing / MRP. A bill of materials lists the component products
 * (and quantities) needed to make one batch of a finished product. A work order
 * produces a quantity of that product: completing it consumes the scaled
 * components and produces the finished goods, both through the stock ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->decimal('output_quantity', 12, 3)->default(1); // units produced per batch
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('bom_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('bills_of_materials')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3); // per one batch (output_quantity units)
            $table->unique(['bom_id', 'component_product_id']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('bom_id')->constrained('bills_of_materials')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3); // finished units to produce
            $table->string('status', 12)->default('draft'); // draft | in_progress | done | cancelled
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('bom_components');
        Schema::dropIfExists('bills_of_materials');
    }
};
