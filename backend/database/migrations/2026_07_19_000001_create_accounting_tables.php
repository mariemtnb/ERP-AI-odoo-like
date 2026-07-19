<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 120);
            // asset | liability | equity | income | expense
            $table->string('type', 12);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('type');
        });

        // Journal entries — the double-entry header
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();     // JE-YYYY-NNNN
            $table->date('entry_date');
            $table->string('memo')->default('');
            // links the entry back to the business document that caused it
            $table->string('reference_type', 20)->default('manual'); // sale|purchase|manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
            $table->index('entry_date');
        });

        // Journal lines — every entry must balance (sum debit === sum credit)
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('label')->default('');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->index('account_id');
        });

        // Default chart of accounts for a trading SME — seeded here so the
        // posting service always has the accounts it needs (incl. in tests).
        $now = now();
        DB::table('accounts')->insert(array_map(
            fn ($a) => $a + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            [
                ['code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
                ['code' => '1100', 'name' => 'Accounts receivable', 'type' => 'asset'],
                ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset'],
                ['code' => '2000', 'name' => 'Accounts payable', 'type' => 'liability'],
                ['code' => '3000', 'name' => 'Owner equity', 'type' => 'equity'],
                ['code' => '4000', 'name' => 'Sales revenue', 'type' => 'income'],
                ['code' => '5000', 'name' => 'Cost of goods sold', 'type' => 'expense'],
                ['code' => '6000', 'name' => 'Operating expenses', 'type' => 'expense'],
            ]
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
