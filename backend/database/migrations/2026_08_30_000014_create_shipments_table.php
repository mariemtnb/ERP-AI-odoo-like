<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping / delivery orders. A shipment can reference a sale, names a carrier
 * and destination, and moves through a guarded lifecycle
 * (pending → shipped → delivered), capturing a tracking number and the shipped
 * and delivered timestamps. It can be cancelled before delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('carrier');
            $table->string('tracking_number')->nullable();
            $table->string('address')->default('');
            $table->string('status', 10)->default('pending'); // pending | shipped | delivered | cancelled
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
