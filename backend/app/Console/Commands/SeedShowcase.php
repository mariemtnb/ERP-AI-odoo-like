<?php

namespace App\Console\Commands;

use App\Models\Pricelist;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Fills every module with a realistic demo dataset by driving the app's own
 * API — the same endpoints the UI uses — so numbering, accounting, stock and
 * every side effect are exactly as a real user would create them. Idempotent:
 * it skips if the showcase data is already present.
 *
 * Run automatically by install.sh, or by hand:  php artisan erp:seed-showcase
 */
class SeedShowcase extends Command
{
    protected $signature = 'erp:seed-showcase {--force : seed even if showcase data already exists}';

    protected $description = 'Populate every module with demo data for testing';

    private string $token = '';

    public function handle(): int
    {
        if (Pricelist::exists() && ! $this->option('force')) {
            $this->info('Showcase data already present — skipping (use --force to add anyway).');

            return self::SUCCESS;
        }

        // Make sure an admin exists to authenticate as (DemoSeeder creates one).
        User::firstOrCreate(['email' => 'admin@erp.local'],
            ['password' => 'Admin123!', 'first_name' => 'Admin', 'role' => 'admin']);

        $login = $this->http(false)->post('/auth/login', ['email' => 'admin@erp.local', 'password' => 'Admin123!']);
        if (! $login->ok()) {
            $this->error('Could not log in to the API — is the backend serving on localhost:8000?');

            return self::FAILURE;
        }
        $this->token = $login->json('access');

        $this->seed();
        $this->info('Showcase data seeded.');

        return self::SUCCESS;
    }

    private function base(): string
    {
        return rtrim((string) config('app.url', 'http://localhost:8000'), '/').'/api/v1';
    }

    private function http(bool $auth = true): PendingRequest
    {
        $r = Http::baseUrl($this->base())->acceptJson()->timeout(30);

        return $auth ? $r->withToken($this->token) : $r;
    }

    /** POST returning the decoded body (or null). */
    private function post(string $path, array $body): ?array
    {
        return $this->http()->post($path, $body)->json();
    }

    private function d(int $offset = 0): string
    {
        return now()->addDays($offset)->toDateString();
    }

    private function seed(): void
    {
        // ---- catalog ----
        $cats = [];
        foreach (['Hardware', 'Bakery'] as $n) {
            $cats[] = $this->post('/categories/', ['name' => $n])['id'] ?? null;
        }
        $prods = [];
        $catalog = [
            ['HW-BOLT', 'Steel Bolt', 0.2, 0.6, $cats[0], 100],
            ['HW-NUT', 'Steel Nut', 0.1, 0.4, $cats[0], 100],
            ['HW-BRACKET', 'Assembly Bracket', 0.0, 3.0, $cats[0], 5],
            ['BK-BREAD', 'Baguette', 0.3, 0.8, $cats[1], 20],
            ['BK-CAKE', 'Sponge Cake', 2.0, 5.5, $cats[1], 8],
        ];
        foreach ($catalog as [$sku, $nm, $cost, $sale, $cat, $mn]) {
            $p = $this->post('/products/', [
                'sku' => $sku, 'name' => $nm, 'cost_price' => $cost, 'sale_price' => $sale,
                'unit' => 'pcs', 'min_stock_level' => $mn, 'category' => $cat,
            ]);
            $prods[] = $p['id'] ?? null;
        }
        $this->post('/warehouses/', ['name' => 'Sfax Depot', 'address' => 'Zone Industrielle']);
        foreach (array_slice($prods, 0, 4) as $pid) {
            $this->post('/stock/movements/', ['product' => $pid, 'movement_type' => 'in', 'quantity' => 80, 'reason' => 'Opening stock']);
        }

        // ---- lots & expiry ----
        $this->post('/lots', ['product' => $prods[4], 'lot_number' => 'LOT-A', 'expiry_date' => $this->d(20), 'quantity' => 6]);
        $this->post('/lots', ['product' => $prods[4], 'lot_number' => 'LOT-B', 'expiry_date' => $this->d(120), 'quantity' => 4]);

        // ---- partners ----
        $sups = [];
        foreach (['Comptoir Metal SARL', 'Farine du Sud'] as $n) {
            $sups[] = $this->post('/suppliers/', ['name' => $n, 'phone' => '71 000 000'])['id'] ?? null;
        }
        $custs = [];
        foreach (['Ahmed Trading', 'Boutique Leila', 'Cafe Central'] as $n) {
            $custs[] = $this->post('/customers/', ['name' => $n, 'email' => strtolower(explode(' ', $n)[0]).'@cust.tn', 'phone' => '22 000 000', 'address' => 'Tunis'])['id'] ?? null;
        }

        // ---- pricelist ----
        $pl = $this->post('/pricelists', ['name' => 'Wholesale', 'is_default' => false]);
        if ($plid = $pl['id'] ?? null) {
            $this->post("/pricelists/{$plid}/rules", ['product_id' => $prods[0], 'mode' => 'discount', 'value' => 10, 'min_qty' => 1]);
            $this->post("/pricelists/{$plid}/rules", ['product_id' => $prods[0], 'mode' => 'fixed', 'value' => 0.5, 'min_qty' => 50]);
            $this->http()->patch("/customers/{$custs[0]}", ['pricelist_id' => $plid]);
        }

        // ---- CRM ----
        foreach ([['Sofiane B.', 'Sofiane SARL'], ['Mariem K.', 'MK Import'], ['Nizar T.', 'Nizar Co']] as [$nm, $co]) {
            $l = $this->post('/leads/', ['name' => $nm, 'company' => $co, 'source' => 'web']);
            if ($id = $l['id'] ?? null) {
                $this->post("/leads/{$id}/activities", ['type' => 'call', 'summary' => 'Intro call — interested']);
            }
        }

        // ---- sales (mixed VAT + discount) ----
        $saleIds = [];
        $specs = [
            [$custs[0], [[$prods[0], 50, 0.6, 19, 5], [$prods[1], 20, 0.4, 7, 0]]],
            [$custs[1], [[$prods[4], 3, 5.5, 13, 0]]],
            [$custs[2], [[$prods[3], 10, 0.8, 19, 0]]],
        ];
        foreach ($specs as [$cid, $lines]) {
            $body = ['customer' => $cid, 'sale_date' => $this->d(0), 'lines' => array_map(
                fn ($l) => ['product' => $l[0], 'quantity' => $l[1], 'unit_price' => $l[2], 'tax_rate' => $l[3], 'discount_pct' => $l[4]], $lines)];
            $s = $this->post('/sales', $body);
            if ($id = $s['id'] ?? null) {
                $saleIds[] = $id;
                $this->post("/sales/{$id}/confirm", []);
            }
        }
        if ($saleIds) {
            $this->post("/sales/{$saleIds[0]}/invoice", []);
            $this->post("/sales/{$saleIds[0]}/email", []);
            // return one unit
            $ret = $this->http()->get("/sales/{$saleIds[0]}/returnable")->json('returnable') ?? [];
            $pid = array_key_first($ret);
            if ($pid !== null) {
                $this->post("/sales/{$saleIds[0]}/credit-notes", ['reason' => 'Damaged', 'lines' => [['product' => (int) $pid, 'quantity' => 1, 'unit_price' => 0.6]]]);
            }
            // chatter
            $this->post("/chatter/sales/{$saleIds[0]}/messages", ['body' => 'Customer confirmed the order by phone.']);
            $this->post("/chatter/sales/{$saleIds[0]}/activities", ['title' => 'Call to arrange delivery', 'due_date' => $this->d(2)]);
            // shipping
            $this->post('/shipments', ['sale' => $saleIds[0], 'customer' => $custs[0], 'carrier' => 'Aramex', 'address' => 'Tunis Centre']);
        }

        // ---- POS ----
        $this->post('/pos/session/open', ['opening_float' => 50]);
        $this->post('/pos/orders', ['lines' => [['product' => $prods[0], 'quantity' => 2], ['product' => $prods[1], 'quantity' => 3]], 'payments' => [['method' => 'cash', 'amount' => 5]]]);

        // ---- subscriptions & marketing ----
        $this->post('/subscriptions', ['customer' => $custs[0], 'description' => 'Monthly maintenance', 'amount' => 120, 'interval' => 'monthly', 'start_date' => $this->d(-30)]);
        $this->post('/subscriptions', ['customer' => $custs[1], 'description' => 'Yearly hosting', 'amount' => 600, 'interval' => 'yearly', 'start_date' => $this->d(-60)]);
        $this->post('/campaigns', ['name' => 'Ramadan promo', 'channel' => 'email', 'subject' => 'Special offers', 'body' => 'Come and see our offers!']);
        $this->post('/campaigns', ['name' => 'Flash SMS', 'channel' => 'sms', 'body' => 'Flash sale today only!']);

        // ---- projects & helpdesk ----
        foreach (['Website revamp', 'Warehouse move'] as $nm) {
            $pr = $this->post('/projects', ['name' => $nm, 'customer' => $custs[0], 'budget_hours' => 40]);
            if ($id = $pr['id'] ?? null) {
                $this->post("/projects/{$id}/tasks", ['name' => 'Kickoff', 'estimate_hours' => 5]);
                $this->post("/projects/{$id}/timesheets", ['work_date' => $this->d(-1), 'hours' => 3, 'billable' => true, 'note' => 'Setup']);
            }
        }
        foreach ([['Broken item in delivery', 'high'], ['Question about invoice', 'normal'], ['Late shipment', 'urgent']] as [$subj, $pr]) {
            $t = $this->post('/tickets', ['subject' => $subj, 'customer' => $custs[0], 'priority' => $pr, 'message' => 'Please help.']);
            if ($id = $t['id'] ?? null) {
                $this->post("/tickets/{$id}/reply", ['body' => 'Thanks, we are looking into it.']);
            }
        }

        // ---- purchases (partial + full) ----
        $pos = [];
        foreach ([[$sups[0], [[$prods[0], 200, 0.2, 19], [$prods[1], 200, 0.1, 19]]], [$sups[1], [[$prods[3], 100, 0.3, 19]]]] as [$sup, $lines]) {
            $body = ['supplier' => $sup, 'order_date' => $this->d(-5), 'lines' => array_map(
                fn ($l) => ['product' => $l[0], 'quantity' => $l[1], 'unit_price' => $l[2], 'tax_rate' => $l[3]], $lines)];
            $po = $this->post('/purchases', $body);
            if (isset($po['id'])) {
                $pos[] = $po;
                $this->post("/purchases/{$po['id']}/confirm", []);
            }
        }
        if ($pos) {
            $l0 = $pos[0]['lines'][0]['id'];
            $this->post("/purchases/{$pos[0]['id']}/receive", ['lines' => [['line' => $l0, 'quantity' => 100]]]); // partial
        }
        if (count($pos) > 1) {
            $this->post("/purchases/{$pos[1]['id']}/receive", []); // full
        }

        // ---- vendor bills (matched + exception) ----
        if (count($pos) > 1) {
            $po = $pos[1];
            $ln = $po['lines'][0];
            $this->post('/vendor-bills', ['supplier' => $po['supplier'], 'purchase_order' => $po['id'], 'bill_date' => $this->d(-1),
                'supplier_ref' => 'INV-778', 'lines' => [['product' => $ln['product'], 'quantity' => $ln['quantity'], 'unit_price' => $ln['unit_price']]]]);
        }
        $this->post('/vendor-bills', ['supplier' => $sups[0], 'bill_date' => $this->d(-1), 'supplier_ref' => 'INV-999',
            'lines' => [['product' => $prods[0], 'quantity' => 500, 'unit_price' => 0.25]]]); // exception

        // ---- reordering & RFQ ----
        foreach (array_slice($prods, 0, 2) as $pid) {
            $this->post('/reorder-rules', ['product' => $pid, 'supplier' => $sups[0], 'min_qty' => 20, 'reorder_qty' => 100]);
        }
        $this->post('/rfqs', ['title' => 'Bulk bolts & nuts', 'due_date' => $this->d(14),
            'lines' => [['product' => $prods[0], 'quantity' => 1000], ['product' => $prods[1], 'quantity' => 1000]]]);

        // ---- manufacturing ----
        $bom = $this->post('/boms', ['product' => $prods[2], 'output_quantity' => 1,
            'components' => [['component' => $prods[0], 'quantity' => 4], ['component' => $prods[1], 'quantity' => 4]]]);
        if ($id = $bom['id'] ?? null) {
            $this->post('/work-orders', ['bom' => $id, 'quantity' => 10]);
        }

        // ---- fixed assets ----
        $this->post('/assets', ['name' => 'Delivery Van', 'category' => 'Vehicles', 'acquisition_date' => $this->d(-90), 'acquisition_cost' => 45000, 'salvage_value' => 4500, 'useful_life_months' => 60]);
        $this->post('/assets', ['name' => 'Forklift', 'category' => 'Equipment', 'acquisition_date' => $this->d(-120), 'acquisition_cost' => 22000, 'salvage_value' => 2000, 'useful_life_months' => 48]);

        // ---- payroll & HR ----
        $emps = [];
        foreach ([['Fatma', 'Gharbi', 2200, true, 3], ['Youssef', 'Amri', 1500, false, 0]] as [$fn, $ln, $sal, $hof, $kids]) {
            $e = $this->post('/employees', ['first_name' => $fn, 'last_name' => $ln, 'base_salary' => $sal, 'job_title' => 'Staff', 'head_of_family' => $hof, 'dependent_children' => $kids]);
            if ($id = $e['id'] ?? null) {
                $emps[] = $id;
            }
        }
        $this->post('/payroll/runs', ['period_month' => now()->startOfMonth()->toDateString()]);
        if ($emps) {
            $e = $emps[0];
            $this->post('/hr/attendance/clock-in', ['employee' => $e, 'time' => '08:30']);
            $this->post('/hr/leave', ['employee' => $e, 'type' => 'annual', 'start_date' => $this->d(3), 'end_date' => $this->d(5), 'reason' => 'Family']);
            $this->post('/hr/expenses', ['employee' => $e, 'claim_date' => $this->d(-1), 'category' => 'travel', 'amount' => 85, 'description' => 'Client visit']);
        }
    }
}
