<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom roles carry a module allowlist: exactly which parts of the app a
 * holder may see and reach. NULL means "no restriction" — the built-in roles
 * (admin/manager/employee) keep their existing, code-driven visibility, so
 * this column only ever narrows a custom role, never a system one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('modules')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }
};
