<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds a super-admin account. Super admin is a superset of admin and is the
 * only role that can grant admin/super-admin roles and manage admin accounts.
 * The existing admin@erp.local stays a plain admin so the two tiers can be
 * demonstrated side by side.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! User::where('email', 'superadmin@erp.local')->exists()) {
            User::create([
                'email' => 'superadmin@erp.local',
                'password' => 'Super123!',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'role' => User::ROLE_SUPER_ADMIN,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        User::where('email', 'superadmin@erp.local')->delete();
    }
};
