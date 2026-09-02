<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moving-average inventory cost.
 *
 * Each receipt updates a product's weighted-average cost, and that average —
 * not the static standard cost — is what relieves inventory as cost of goods
 * sold. This keeps the Inventory account in step with the real value of stock
 * on hand even when purchase prices move between orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('avg_cost', 12, 4)->default(0)->after('cost_price');
        });

        // Seed the average from the standard cost so nothing starts at zero.
        DB::statement('UPDATE products SET avg_cost = cost_price');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('avg_cost');
        });
    }
};
