<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Budgets and budget-vs-actual.
 *
 * A budget is a named period with a planned amount per GL account. The actuals
 * come straight from the ledger (the account's posted movement over the same
 * period, via the trial balance), so budget-vs-actual is always in step with
 * the books — nothing to re-post or re-sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            // draft | active | closed
            $table->string('status', 10)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('account_code', 10);
            $table->decimal('amount', 14, 3);   // planned, base currency
            $table->string('notes')->default('');
            $table->timestamps();
            $table->unique(['budget_id', 'account_code']);
        });

        if (! DB::table('feature_flags')->where('key', 'budgets')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'budgets', 'name' => 'Budgets',
                'description' => 'Plan amounts per account and compare against the ledger',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        DB::table('feature_flags')->where('key', 'budgets')->delete();
    }
};
