<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed assets with straight-line depreciation. An asset depreciates from its
 * acquisition cost down to a salvage value over a useful life in months; each
 * run posts a depreciation entry and increases the accumulated depreciation,
 * never taking the book value below salvage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 60)->default('');
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 14, 2);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->string('method', 20)->default('straight_line');
            $table->decimal('accumulated_depreciation', 14, 2)->default(0);
            $table->string('status', 12)->default('active'); // active | disposed
            $table->date('disposed_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('period'); // first day of the depreciated month
            $table->decimal('amount', 14, 2);
            $table->decimal('book_value_after', 14, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['fixed_asset_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
        Schema::dropIfExists('fixed_assets');
    }
};
