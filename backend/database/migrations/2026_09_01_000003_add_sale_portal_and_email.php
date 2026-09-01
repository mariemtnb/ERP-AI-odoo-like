<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sharing a sale with its customer: a hard-to-guess portal token lets them
 * view it on a public page without an account, and `emailed_at` records when
 * the document was last sent to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('portal_token', 64)->nullable()->unique()->after('number');
            $table->timestamp('emailed_at')->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['portal_token', 'emailed_at']);
        });
    }
};
