<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement: requests for quotation (RFQ) and supplier bids. An RFQ lists the
 * products and quantities to buy; suppliers submit a bid pricing each line.
 * Bids are compared by total, and awarding one turns the winning bid into a
 * draft purchase order while rejecting the rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('title');
            $table->string('status', 12)->default('open'); // open | awarded | closed
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('rfq_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
        });

        Schema::create('rfq_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('status', 12)->default('submitted'); // submitted | awarded | rejected
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('note')->default('');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['rfq_id', 'supplier_id']);
        });

        Schema::create('rfq_bid_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained('rfq_bids')->cascadeOnDelete();
            $table->foreignId('rfq_line_id')->constrained('rfq_lines')->cascadeOnDelete();
            $table->decimal('unit_price', 14, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_bid_lines');
        Schema::dropIfExists('rfq_bids');
        Schema::dropIfExists('rfq_lines');
        Schema::dropIfExists('rfqs');
    }
};
