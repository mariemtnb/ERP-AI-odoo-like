<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BI / report builder. A saved report captures a small query spec — a data
 * source, a dimension to group by, and a measure to aggregate — that the
 * builder runs on demand against the operational tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source', 20)->default('sales');
            $table->string('group_by', 20); // month | customer | product
            $table->string('measure', 20);  // total | count
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
