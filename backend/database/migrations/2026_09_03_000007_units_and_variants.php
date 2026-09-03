<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Units of measure (with conversion) and product variants.
 *
 * Units of measure live in categories (unit / weight / volume / length); within
 * a category each unit has a factor expressed in the category's reference unit,
 * so quantities convert (buy in boxes, stock in pieces). Variants let one
 * product template fan out into concrete products by attribute (size, colour)
 * — each variant is an ordinary product carrying its attribute values and
 * pointing back to its template.
 *
 * Both are additive: products keep their existing free-text unit and single-SKU
 * shape until a unit or template is assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('category', 20);                 // unit|weight|volume|length
            $table->decimal('factor', 18, 8)->default(1);   // value in the category's reference unit
            $table->boolean('is_reference')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();               // e.g. Size, Colour
            $table->timestamps();
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->string('value');                        // e.g. Small, Red
            $table->timestamps();
            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained('product_attribute_values')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'attribute_value_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('uom_id')->nullable()->after('unit')->constrained('units_of_measure')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->after('uom_id')->constrained('products')->nullOnDelete();
        });

        $now = now();
        DB::table('units_of_measure')->insert(array_map(fn ($u) => $u + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now], [
            ['code' => 'unit', 'name' => 'Unit', 'category' => 'unit', 'factor' => 1, 'is_reference' => true],
            ['code' => 'dozen', 'name' => 'Dozen', 'category' => 'unit', 'factor' => 12, 'is_reference' => false],
            ['code' => 'pair', 'name' => 'Pair', 'category' => 'unit', 'factor' => 2, 'is_reference' => false],
            ['code' => 'box', 'name' => 'Box (24)', 'category' => 'unit', 'factor' => 24, 'is_reference' => false],
            ['code' => 'kg', 'name' => 'Kilogram', 'category' => 'weight', 'factor' => 1, 'is_reference' => true],
            ['code' => 'g', 'name' => 'Gram', 'category' => 'weight', 'factor' => 0.001, 'is_reference' => false],
            ['code' => 'ton', 'name' => 'Tonne', 'category' => 'weight', 'factor' => 1000, 'is_reference' => false],
            ['code' => 'L', 'name' => 'Litre', 'category' => 'volume', 'factor' => 1, 'is_reference' => true],
            ['code' => 'mL', 'name' => 'Millilitre', 'category' => 'volume', 'factor' => 0.001, 'is_reference' => false],
            ['code' => 'm', 'name' => 'Metre', 'category' => 'length', 'factor' => 1, 'is_reference' => true],
            ['code' => 'cm', 'name' => 'Centimetre', 'category' => 'length', 'factor' => 0.01, 'is_reference' => false],
        ]));

        foreach (['uom' => 'Units of measure', 'variants' => 'Product variants'] as $key => $name) {
            if (! DB::table('feature_flags')->where('key', $key)->exists()) {
                DB::table('feature_flags')->insert([
                    'key' => $key, 'name' => $name, 'description' => $name.' support',
                    'enabled' => true, 'is_locked' => false, 'company_id' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uom_id');
            $table->dropConstrainedForeignId('template_id');
        });
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('units_of_measure');
        DB::table('feature_flags')->whereIn('key', ['uom', 'variants'])->delete();
    }
};
