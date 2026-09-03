<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing routings and work centres.
 *
 * A work centre is a place or machine where work happens, with an hourly cost.
 * A bill of materials can carry an ordered routing — a list of operations, each
 * at a work centre for a number of minutes — so the labour cost and time to
 * build are known, not just the material cost. Additive: a BOM without a routing
 * behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_centres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->decimal('cost_per_hour', 10, 3)->default(0);
            $table->unsignedInteger('capacity_minutes_per_day')->default(480); // one 8h shift
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('bills_of_materials')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->string('name');
            $table->foreignId('work_centre_id')->nullable()->constrained('work_centres')->nullOnDelete();
            $table->decimal('minutes', 10, 2)->default(0);   // time to make one BOM output batch
            $table->timestamps();
        });

        $now = now();
        DB::table('work_centres')->insert([
            ['code' => 'ASSEMBLY', 'name' => 'Assembly line', 'cost_per_hour' => 30, 'capacity_minutes_per_day' => 480, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PACKING', 'name' => 'Packing station', 'cost_per_hour' => 18, 'capacity_minutes_per_day' => 480, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        if (! DB::table('feature_flags')->where('key', 'manufacturing')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'manufacturing', 'name' => 'Manufacturing',
                'description' => 'Bills of materials, routings and work orders',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_operations');
        Schema::dropIfExists('work_centres');
    }
};
