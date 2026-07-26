<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the columns the app actually filters and sorts on.
 *
 * Every one of these backs a query that already exists in a controller or
 * service. Foreign keys are not indexed automatically on PostgreSQL, so the
 * partner-history and per-customer views were doing sequential scans that only
 * show up once a company has a few years of data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // PaymentController filters by each of these.
            $table->index('customer_id', 'payments_customer_idx');
            $table->index('supplier_id', 'payments_supplier_idx');
            $table->index('bank_account_id', 'payments_bank_account_idx');
            $table->index('installment_id', 'payments_installment_idx');
            // "cash vs bank collected" scans direction + method + date.
            $table->index(['direction', 'method', 'payment_date'], 'payments_collection_idx');
        });

        Schema::table('payment_instruments', function (Blueprint $table) {
            // Customer credit view and per-partner instrument lists.
            $table->index('customer_id', 'instruments_customer_idx');
            $table->index('supplier_id', 'instruments_supplier_idx');
            $table->index('bank_account_id', 'instruments_bank_account_idx');
            // The outstanding/overdue portfolio query filters kind + status.
            $table->index(['kind', 'status'], 'instruments_kind_status_idx');
        });

        Schema::table('installment_plans', function (Blueprint $table) {
            $table->index('customer_id', 'plans_customer_idx');
            $table->index('supplier_id', 'plans_supplier_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            // Sales list sorts by created_at and filters by status/customer.
            $table->index(['status', 'sale_date'], 'sales_status_date_idx');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['status', 'order_date'], 'purchases_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_customer_idx');
            $table->dropIndex('payments_supplier_idx');
            $table->dropIndex('payments_bank_account_idx');
            $table->dropIndex('payments_installment_idx');
            $table->dropIndex('payments_collection_idx');
        });
        Schema::table('payment_instruments', function (Blueprint $table) {
            $table->dropIndex('instruments_customer_idx');
            $table->dropIndex('instruments_supplier_idx');
            $table->dropIndex('instruments_bank_account_idx');
            $table->dropIndex('instruments_kind_status_idx');
        });
        Schema::table('installment_plans', function (Blueprint $table) {
            $table->dropIndex('plans_customer_idx');
            $table->dropIndex('plans_supplier_idx');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_status_date_idx');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('purchases_status_date_idx');
        });
    }
};
