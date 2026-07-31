<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/** Demo dataset: accounts, catalog, stock and one full business cycle. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@erp.local'],
            ['password' => 'Admin123!', 'first_name' => 'Admin', 'role' => 'admin'],
        );
        User::firstOrCreate(
            ['email' => 'manager@erp.local'],
            ['password' => 'Manager123!', 'first_name' => 'Mounir', 'role' => 'manager'],
        );
        User::firstOrCreate(
            ['email' => 'employee@erp.local'],
            ['password' => 'Employee123!', 'first_name' => 'Emma', 'role' => 'employee'],
        );

        if (Product::exists()) {
            return; // demo data already present
        }

        $beverages = Category::create(['name' => 'Beverages']);
        $snacks = Category::create(['name' => 'Snacks']);

        $water = Product::create([
            'sku' => 'BEV-001', 'name' => 'Mineral Water 1L', 'category_id' => $beverages->id,
            'cost_price' => 0.40, 'sale_price' => 0.90, 'min_stock_level' => 20,
        ]);
        $juice = Product::create([
            'sku' => 'BEV-002', 'name' => 'Orange Juice 25cl', 'category_id' => $beverages->id,
            'cost_price' => 0.80, 'sale_price' => 1.50, 'min_stock_level' => 15,
        ]);
        $chips = Product::create([
            'sku' => 'SNK-001', 'name' => 'Potato Chips 90g', 'category_id' => $snacks->id,
            'cost_price' => 0.60, 'sale_price' => 1.20, 'min_stock_level' => 10,
        ]);

        foreach ([[$water, 100], [$juice, 40], [$chips, 8]] as [$product, $qty]) {
            StockService::recordMovement(
                productId: $product->id, movementType: 'in', quantity: $qty,
                user: $admin, reason: 'initial stock',
            );
        }

        $supplier = Supplier::create([
            'name' => 'SOTUFAB Distribution', 'email' => 'contact@sotufab.tn',
            'phone' => '+216 71 000 000',
        ]);
        $customer = Customer::create([
            'name' => 'Ahmed Ben Ali', 'email' => 'ahmed@example.tn',
            'phone' => '+216 20 123 456',
        ]);
        Customer::create(['name' => 'Fatma Trabelsi', 'phone' => '+216 22 555 777']);

        // One received purchase order.
        $po = PurchaseOrder::create([
            'number' => DocumentService::nextNumber('PO', PurchaseOrder::class),
            'supplier_id' => $supplier->id, 'order_date' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);
        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'product_id' => $water->id,
            'quantity' => 50, 'unit_price' => 0.40,
        ]);
        $po->load('lines')->recomputeTotal();
        DocumentService::confirmPurchase($po, $admin);
        DocumentService::receivePurchase($po, $admin);

        // One confirmed sale with invoice.
        $sale = Sale::create([
            'number' => DocumentService::nextNumber('SO', Sale::class),
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);
        SaleLine::create([
            'sale_id' => $sale->id, 'product_id' => $water->id,
            'quantity' => 30, 'unit_price' => 0.90,
        ]);
        $sale->load('lines')->recomputeTotal();
        DocumentService::confirmSale($sale, $admin);
        DocumentService::generateInvoice($sale);

        // Tunisian treasury demo: cheques, traites, installments, statement.
        $this->call(TunisiaDemoSeeder::class);
    }
}
