<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM opportunity pipeline.
 *
 * Leads move along configurable stages, each with a default win probability and
 * a won/lost marker. A lead carries an expected revenue and (optionally) its own
 * probability, so the pipeline can be forecast weighted by likelihood. Additive:
 * leads keep their existing flat status until a stage is assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->unsignedSmallInteger('probability')->default(0);  // 0..100
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('stage_id')->nullable()->after('status')->constrained('crm_stages')->nullOnDelete();
            $table->decimal('expected_revenue', 14, 3)->default(0)->after('stage_id');
            $table->unsignedSmallInteger('probability')->nullable()->after('expected_revenue'); // override of stage default
            $table->string('lost_reason', 200)->default('')->after('probability');
        });

        $now = now();
        DB::table('crm_stages')->insert(array_map(fn ($s) => $s + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now], [
            ['name' => 'New', 'sequence' => 1, 'probability' => 10, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Qualified', 'sequence' => 2, 'probability' => 30, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Proposition', 'sequence' => 3, 'probability' => 60, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Negotiation', 'sequence' => 4, 'probability' => 80, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Won', 'sequence' => 5, 'probability' => 100, 'is_won' => true, 'is_lost' => false],
            ['name' => 'Lost', 'sequence' => 6, 'probability' => 0, 'is_won' => false, 'is_lost' => true],
        ]));

        if (! DB::table('feature_flags')->where('key', 'crm')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'crm', 'name' => 'CRM', 'description' => 'Leads and the opportunity pipeline',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_id');
            $table->dropColumn(['expected_revenue', 'probability', 'lost_reason']);
        });
        Schema::dropIfExists('crm_stages');
    }
};
