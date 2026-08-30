<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions / recurring billing. A subscription bills a customer a fixed
 * amount on a monthly, quarterly or yearly cadence. Running billing generates
 * an invoice for every subscription whose next date has arrived and advances
 * that date by one interval — idempotent, so running twice never double-bills.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->string('interval', 10); // monthly | quarterly | yearly
            $table->string('status', 10)->default('active'); // active | paused | cancelled
            $table->date('start_date');
            $table->date('next_invoice_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'next_invoice_date']);
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->date('period_start');
            $table->decimal('amount', 14, 2);
            $table->timestamp('issued_at')->useCurrent();
            $table->unique(['subscription_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
    }
};
