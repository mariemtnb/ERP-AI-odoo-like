<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency foundation: a currency registry and dated exchange rates.
 * Rates are expressed as the value of one unit of the currency in the base
 * currency (base = 1). TND is seeded as the base to match the localization
 * layer. Conversion between any two currencies goes through the base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->string('code', 3)->primary(); // ISO 4217
            $table->string('name');
            $table->string('symbol', 8)->default('');
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnDelete();
            $table->decimal('rate', 18, 8); // base-currency value of 1 unit of this currency
            $table->date('as_of');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['currency_code', 'as_of']);
        });

        // Seed the base currency to match the Tunisian localization (3 decimals).
        DB::table('currencies')->insert([
            'code' => 'TND', 'name' => 'Tunisian Dinar', 'symbol' => 'DT',
            'decimals' => 3, 'is_base' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
