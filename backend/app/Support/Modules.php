<?php

namespace App\Support;

/**
 * The catalogue of assignable modules.
 *
 * A "module" is one navigable area of the app — the same granularity the
 * sidebar and the feature flags use. A custom role stores a subset of these
 * keys as its allowlist; the frontend then shows only those areas and the
 * `me/context` endpoint hands the same list back so both agree.
 *
 * The pure-administration areas (users, settings, the permission engine
 * itself) are deliberately absent: they stay gated to real admins, so a
 * custom operational role can never be pointed at them by mistake.
 */
final class Modules
{
    /** key => human label, grouped roughly as the sidebar is. */
    public const CATALOG = [
        'inventory'     => 'Products & Inventory',
        'lots'          => 'Lots & Expiry',
        'manufacturing' => 'Manufacturing',
        'purchasing'    => 'Suppliers & Purchases',
        'reordering'    => 'Reordering',
        'rfq'           => 'RFQs',
        'sales'         => 'Customers & Sales',
        'pos'           => 'Point of Sale',
        'subscriptions' => 'Subscriptions',
        'returns'       => 'Returns',
        'shipping'      => 'Shipping',
        'marketing'     => 'Marketing',
        'crm'           => 'CRM',
        'projects'      => 'Projects',
        'helpdesk'      => 'Helpdesk',
        'profit'        => 'Profit',
        'accounting'    => 'Accounting',
        'assets'        => 'Fixed Assets',
        'payroll'       => 'Payroll',
        'hr'            => 'Human Resources',
        'treasury'      => 'Cheques & Installments',
        'banking'       => 'Banking & Reconciliation',
        'reports'       => 'Reports',
        'bi'            => 'Report Builder',
        'ai'            => 'AI Assistant',
    ];

    /** @return list<string> every valid module key */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    /** @return array<int,array{key:string,label:string}> for the picker UI */
    public static function list(): array
    {
        $out = [];
        foreach (self::CATALOG as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label];
        }

        return $out;
    }

    /** Keep only keys we recognise, de-duplicated and in catalogue order. */
    public static function sanitize(array $keys): array
    {
        return array_values(array_filter(self::keys(), fn ($k) => in_array($k, $keys, true)));
    }
}
