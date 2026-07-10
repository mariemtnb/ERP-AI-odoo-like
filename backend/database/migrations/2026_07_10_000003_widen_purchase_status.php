<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'pending_approval' (16 chars) exceeds the original varchar(12).
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('status', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('status', 12)->change();
        });
    }
};
