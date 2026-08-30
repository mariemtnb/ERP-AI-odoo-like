<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales returns / credit notes. A credit note credits part or all of a
 * confirmed sale: it restocks the returned goods (through StockService) and
 * posts the mirror-image accounting entry (through AccountingService), so the
 * books and the stock ledger stay in step, append-only, like every other flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('reason')->default('');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->boolean('restocked')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sale_id', 'created_at']);
        });

        Schema::create('credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_lines');
        Schema::dropIfExists('credit_notes');
    }
};
