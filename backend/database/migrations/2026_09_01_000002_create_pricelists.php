<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pricelists & discounts.
 *
 * A pricelist is a named set of rules that override a product's base sale
 * price — a fixed price or a percentage off, optionally from a minimum
 * quantity, targeted at one product, a whole category, or everything. A
 * customer can be put on a pricelist; otherwise the default one (if any)
 * applies. Sale and POS lines also gain a per-line discount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricelists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('notes', 255)->default('');
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('pricelist_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricelist_id')->constrained('pricelists')->cascadeOnDelete();
            // Target: a product, or a category, or (both null) every product.
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->decimal('min_qty', 12, 3)->default(0);
            $table->string('mode', 10)->default('fixed');   // fixed | discount
            $table->decimal('value', 12, 3)->default(0);     // fixed price, or percent off
            $table->timestamps();
            $table->index(['pricelist_id', 'product_id']);
            $table->index(['pricelist_id', 'category_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('pricelist_id')->nullable()->after('id')
                ->constrained('pricelists')->nullOnDelete();
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->decimal('discount_pct', 5, 2)->default(0)->after('unit_price');
        });

        Schema::table('pos_order_lines', function (Blueprint $table) {
            $table->decimal('discount_pct', 5, 2)->default(0)->after('unit_price');
        });

        // On by default so the module is usable straight away; can be switched
        // off per deployment like every other module.
        if (! DB::table('feature_flags')->where('key', 'pricelists')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'pricelists', 'name' => 'Pricelists & discounts',
                'description' => 'Customer and quantity based pricing',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pos_order_lines', fn (Blueprint $t) => $t->dropColumn('discount_pct'));
        Schema::table('sale_lines', fn (Blueprint $t) => $t->dropColumn('discount_pct'));
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricelist_id');
        });
        Schema::dropIfExists('pricelist_rules');
        Schema::dropIfExists('pricelists');
        DB::table('feature_flags')->where('key', 'pricelists')->delete();
    }
};
