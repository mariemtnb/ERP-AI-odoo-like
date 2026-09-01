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
        'vendor_bills'  => 'Vendor Bills',
        'reordering'    => 'Reordering',
        'rfq'           => 'RFQs',
        'sales'         => 'Customers & Sales',
        'pricelists'    => 'Pricelists & Discounts',
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

    /**
     * First API path segment → module key.
     *
     * This is the server-side twin of the frontend `MODULE_BY_PATH`: it maps a
     * REST resource ("tickets", "pos", "boms") to the module it belongs to, so
     * a request can be checked against a role's allowlist. Segments absent here
     * (auth, me, notifications, dashboard, the admin console, …) carry no module
     * and are never restricted by this map — they are gated elsewhere.
     */
    public const SEGMENT_MODULE = [
        // inventory & catalogue
        'products' => 'inventory', 'categories' => 'inventory', 'stock' => 'inventory',
        'warehouses' => 'inventory',
        'lots' => 'lots',
        'boms' => 'manufacturing', 'work-orders' => 'manufacturing',
        // purchasing
        'suppliers' => 'purchasing', 'purchases' => 'purchasing',
        'vendor-bills' => 'vendor_bills',
        'reorder-rules' => 'reordering', 'reorder-run' => 'reordering', 'reorder-suggestions' => 'reordering',
        'rfqs' => 'rfq',
        // sales
        'customers' => 'sales', 'sales' => 'sales',
        'pricelists' => 'pricelists', 'pricelist-rules' => 'pricelists', 'pricing' => 'pricelists',
        'pos' => 'pos',
        'subscriptions' => 'subscriptions',
        'credit-notes' => 'returns',
        'shipments' => 'shipping',
        'campaigns' => 'marketing',
        'leads' => 'crm',
        // services
        'projects' => 'projects',
        'tickets' => 'helpdesk',
        // finance
        'owner' => 'profit',
        'accounting' => 'accounting',
        'assets' => 'assets',
        // people
        'payroll' => 'payroll', 'advances' => 'payroll',
        'attendance' => 'hr', 'leave' => 'hr', 'expenses' => 'hr', 'employees' => 'hr',
        // treasury
        'instruments' => 'treasury', 'installment-plans' => 'treasury', 'installments' => 'treasury',
        'banks' => 'banking', 'bank-accounts' => 'banking', 'bank-transactions' => 'banking',
        'reconciliation' => 'banking',
        // insights
        'reports' => 'reports',
        'bi' => 'bi',
        'agent' => 'ai',
    ];

    /** The module a top-level API segment belongs to, or null when it has none. */
    public static function forSegment(string $segment): ?string
    {
        return self::SEGMENT_MODULE[$segment] ?? null;
    }

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
