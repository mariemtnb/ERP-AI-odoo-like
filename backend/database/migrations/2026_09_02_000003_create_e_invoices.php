<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tunisian electronic invoicing (TTN «El Fatoora»).
 *
 * An invoiced sale is turned into a TEIF (Tunisian Electronic Invoice Format)
 * XML document and submitted to TTN, which accepts or rejects it and returns a
 * reference. Each sale has at most one e-invoice row, carrying the generated
 * XML and the current lifecycle status. The provider is pluggable — a built-in
 * sandbox runs the whole flow with no credentials, and the real TTN adapter
 * plugs in behind the same interface.
 *
 * The buyer's fiscal id (matricule fiscal) is needed for a B2B e-invoice, so
 * customers gain a nullable tax_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('provider', 20)->default('mock');
            // generated | submitted | accepted | rejected
            $table->string('status', 12)->default('generated');
            $table->string('ttn_ref', 120)->nullable();   // reference returned by TTN
            $table->longText('xml');                        // the TEIF document
            $table->text('error')->nullable();              // rejection reason, if any
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        if (! Schema::hasColumn('customers', 'tax_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('tax_id', 30)->nullable()->after('address');
            });
        }

        if (! DB::table('feature_flags')->where('key', 'einvoicing')->exists()) {
            DB::table('feature_flags')->insert([
                'key' => 'einvoicing', 'name' => 'E-invoicing (TTN)',
                'description' => 'Generate and submit Tunisian electronic invoices to TTN',
                'enabled' => true, 'is_locked' => false, 'company_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('e_invoices');
        if (Schema::hasColumn('customers', 'tax_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('tax_id');
            });
        }
        DB::table('feature_flags')->where('key', 'einvoicing')->delete();
    }
};
