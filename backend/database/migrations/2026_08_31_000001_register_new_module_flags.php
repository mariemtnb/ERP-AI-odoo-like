<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registers the modules added in this release as feature flags so an admin can
 * switch each on or off from Administration → Modules, and the API and sidebar
 * gate on them. pos / hr / manufacturing already existed as (disabled)
 * placeholders and are now enabled since the modules are implemented.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $flags = [
            ['key' => 'pos', 'name' => 'Point of Sale'],
            ['key' => 'returns', 'name' => 'Sales returns & credit notes'],
            ['key' => 'lots', 'name' => 'Lots & expiry tracking'],
            ['key' => 'reordering', 'name' => 'Reordering & replenishment'],
            ['key' => 'currencies', 'name' => 'Multi-currency'],
            ['key' => 'hr', 'name' => 'Human resources'],
            ['key' => 'assets', 'name' => 'Fixed assets'],
            ['key' => 'manufacturing', 'name' => 'Manufacturing'],
            ['key' => 'projects', 'name' => 'Projects & timesheets'],
            ['key' => 'rfq', 'name' => 'Procurement (RFQ)'],
            ['key' => 'helpdesk', 'name' => 'Helpdesk'],
            ['key' => 'subscriptions', 'name' => 'Subscriptions'],
            ['key' => 'marketing', 'name' => 'Marketing'],
            ['key' => 'shipping', 'name' => 'Shipping'],
            ['key' => 'bi', 'name' => 'Report builder'],
        ];

        foreach ($flags as $f) {
            DB::table('feature_flags')->updateOrInsert(
                ['key' => $f['key']],
                [
                    'name' => $f['name'],
                    'enabled' => true,
                    'is_locked' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('feature_flags')->whereIn('key', [
            'returns', 'lots', 'reordering', 'currencies', 'assets', 'projects',
            'rfq', 'helpdesk', 'subscriptions', 'marketing', 'shipping', 'bi',
        ])->delete();
        // pos / hr / manufacturing pre-existed — turn them back off rather than delete.
        DB::table('feature_flags')->whereIn('key', ['pos', 'hr', 'manufacturing'])
            ->update(['enabled' => false]);
    }
};
