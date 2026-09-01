<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online payments: a customer pays a shared sale from the portal through a
 * payment gateway. Each attempt is one row, moved from pending to paid when
 * the gateway confirms; on confirmation the money is posted to the ledger
 * (Dr bank / Cr receivable). The provider is pluggable — a built-in sandbox
 * runs the whole flow with no real money, and a real Tunisian gateway plugs in
 * behind the same interface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('token', 64)->unique();          // the pay-page link
            $table->decimal('amount', 14, 2);
            $table->string('provider', 20)->default('mock');
            $table->string('status', 12)->default('pending'); // pending|paid|failed|cancelled
            $table->string('gateway_ref', 120)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['sale_id', 'status']);
        });

        if (! DB::table('feature_flags')->where('key', 'online_payments')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'online_payments', 'name' => 'Online payments',
                'description' => 'Let customers pay shared documents from the portal',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
        DB::table('feature_flags')->where('key', 'online_payments')->delete();
    }
};
