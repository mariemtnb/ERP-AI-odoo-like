<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AR dunning: automated follow-ups on overdue invoices.
 *
 * A small ladder of reminder levels (each firing once an invoice is a certain
 * number of days overdue) drives escalating reminders. Each reminder actually
 * sent is logged once per sale-and-level, so a run is idempotent — the same
 * level is never sent twice for the same invoice. Levels are configuration, so
 * the cadence and wording change without a deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('level')->unique();   // 1, 2, 3 …
            $table->unsignedSmallInteger('days_overdue');      // fires at ≥ this many days
            $table->string('name');
            $table->text('message');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dunning_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->unsignedInteger('days_overdue');
            $table->decimal('outstanding', 14, 3);
            $table->string('emailed_to')->default('');
            $table->boolean('emailed')->default(false);
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['sale_id', 'level']);   // one reminder per level per invoice
        });

        $now = now();
        DB::table('dunning_levels')->insert([
            ['level' => 1, 'days_overdue' => 7, 'name' => 'Friendly reminder',
                'message' => 'This is a friendly reminder that the invoice below is now past due. Please arrange payment at your earliest convenience.',
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['level' => 2, 'days_overdue' => 30, 'name' => 'Second notice',
                'message' => 'Our records show the invoice below remains unpaid. Please settle it promptly to avoid further follow-up.',
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['level' => 3, 'days_overdue' => 60, 'name' => 'Final notice',
                'message' => 'This is a final notice for the overdue invoice below. Please contact us immediately to settle the outstanding amount.',
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        if (! DB::table('feature_flags')->where('key', 'dunning')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'dunning', 'name' => 'AR dunning',
                'description' => 'Automated follow-ups on overdue invoices',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_logs');
        Schema::dropIfExists('dunning_levels');
        DB::table('feature_flags')->where('key', 'dunning')->delete();
    }
};
