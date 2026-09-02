<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\InstrumentController;
use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| /api/v1 — same contract as the previous DRF backend.
| RBAC matrix (docs/ARCHITECTURE.md §3) is expressed in the route groups:
|   role:admin                → users module
|   role:admin,manager        → catalog/stock/supplier/purchase writes
|   any authenticated         → reads; customers & sales POST (employees too)
*/

Route::prefix('v1')->group(function () {

    // --- auth ---
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    // Refresh was unthrottled: a stolen refresh token could be exercised in a
    // tight loop, and the endpoint is an oracle for guessing valid tokens.
    Route::post('auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');
    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('throttle:30,1');
    // Public password-reset flow. Tight throttle: these are unauthenticated and
    // a target for abuse (enumeration, token guessing).
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');

    // Public customer portal: view a shared sale by its unguessable token,
    // and pay it online through the configured gateway (sandbox by default).
    Route::get('portal/sales/{token}', [\App\Http\Controllers\PortalController::class, 'sale'])
        ->middleware('throttle:60,1');
    Route::post('portal/sales/{token}/pay', [\App\Http\Controllers\PortalController::class, 'pay'])
        ->middleware(['feature:online_payments', 'throttle:20,1']);
    Route::get('portal/pay/{payToken}', [\App\Http\Controllers\PortalController::class, 'payIntent'])
        ->middleware(['feature:online_payments', 'throttle:60,1']);
    Route::post('portal/pay/{payToken}/confirm', [\App\Http\Controllers\PortalController::class, 'confirmPay'])
        ->middleware(['feature:online_payments', 'throttle:20,1']);

    /*
    | `active` runs on every authenticated request: deactivating an account has
    | to revoke access immediately, not whenever the current token happens to
    | expire. `throttle:api` is a blanket ceiling so no endpoint is unbounded.
    */
    Route::middleware(['auth:api', 'active', 'throttle:api', 'module.access'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        // Self-service profile edit (name, email). Role/active stay admin-only.
        Route::match(['put', 'patch'], 'auth/profile', [AuthController::class, 'updateProfile'])
            ->middleware('throttle:20,1');
        // Brute-forcing current_password was previously unbounded.
        Route::post('auth/change-password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:10,1');

        // --- catalog & stock: everyone reads, managers/admins write ---
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{category}', [CategoryController::class, 'show']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::get('stock/movements', [StockMovementController::class, 'index']);
        Route::get('stock/movements/{movement}', [StockMovementController::class, 'show']);

        // --- warehouses: everyone reads; managers/admins manage & transfer ---
        Route::get('warehouses', [\App\Http\Controllers\WarehouseController::class, 'index']);
        Route::get('warehouses/stock', [\App\Http\Controllers\WarehouseController::class, 'stock']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('warehouses', [\App\Http\Controllers\WarehouseController::class, 'store']);
            Route::match(['put', 'patch'], 'warehouses/{warehouse}', [\App\Http\Controllers\WarehouseController::class, 'update']);
            Route::post('warehouses/transfer', [\App\Http\Controllers\WarehouseController::class, 'transfer']);
        });

        Route::middleware('role:admin,manager')->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
            Route::post('products', [ProductController::class, 'store']);
            Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);
            Route::post('stock/movements', [StockMovementController::class, 'store']);
        });

        // --- customers: everyone reads & creates; managers/admins modify ---
        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::get('customers/{id}/history', [CustomerController::class, 'history']);
        Route::get('customers/{id}', [CustomerController::class, 'showById']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::match(['put', 'patch'], 'customers/{id}', [CustomerController::class, 'updateById']);
            Route::delete('customers/{id}', [CustomerController::class, 'destroyById']);
        });

        // --- suppliers: everyone reads; managers/admins write ---
        Route::get('suppliers', [SupplierController::class, 'index']);
        Route::get('suppliers/{id}/history', [SupplierController::class, 'history']);
        Route::get('suppliers/{id}', [SupplierController::class, 'showById']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('suppliers', [SupplierController::class, 'store']);
            Route::match(['put', 'patch'], 'suppliers/{id}', [SupplierController::class, 'updateById']);
            Route::delete('suppliers/{id}', [SupplierController::class, 'destroyById']);
        });

        // --- purchases: everyone reads; managers/admins write ---
        Route::get('purchases', [PurchaseOrderController::class, 'index']);
        Route::get('purchases/{purchase}', [PurchaseOrderController::class, 'show']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('purchases', [PurchaseOrderController::class, 'store']);
            Route::match(['put', 'patch'], 'purchases/{purchase}', [PurchaseOrderController::class, 'update']);
            Route::delete('purchases/{purchase}', [PurchaseOrderController::class, 'destroy']);
            Route::post('purchases/{purchase}/confirm', [PurchaseOrderController::class, 'confirm']);
            Route::post('purchases/{purchase}/receive', [PurchaseOrderController::class, 'receive']);
            Route::post('purchases/{purchase}/cancel', [PurchaseOrderController::class, 'cancel']);
        });
        // Hierarchical approval of large orders: admin only.
        Route::middleware('role:admin')->group(function () {
            Route::post('purchases/{purchase}/approve', [PurchaseOrderController::class, 'approve']);
            Route::post('purchases/{purchase}/reject', [PurchaseOrderController::class, 'reject']);
        });

        // --- purchase requisitions + approval engine, behind the flag ---
        // Anyone raises a requisition; the chain routes it, and only its
        // configured approver role can sign each step (checked in the engine).
        Route::middleware('feature:requisitions')->group(function () {
            Route::get('requisitions', [\App\Http\Controllers\RequisitionController::class, 'index']);
            Route::post('requisitions', [\App\Http\Controllers\RequisitionController::class, 'store']);
            Route::get('requisitions/{requisition}', [\App\Http\Controllers\RequisitionController::class, 'show']);
            Route::post('requisitions/{requisition}/submit', [\App\Http\Controllers\RequisitionController::class, 'submit']);
            Route::post('requisitions/{requisition}/convert', [\App\Http\Controllers\RequisitionController::class, 'convert'])
                ->middleware('role:admin,manager');
            Route::get('approvals/pending', [\App\Http\Controllers\ApprovalController::class, 'pending']);
            Route::post('approvals/{approval}/act', [\App\Http\Controllers\ApprovalController::class, 'act']);
        });

        // --- vendor bills (3-way match): managers/admins record and clear ---
        Route::middleware('feature:vendor_bills')->group(function () {
            Route::get('vendor-bills', [\App\Http\Controllers\VendorBillController::class, 'index']);
            Route::get('vendor-bills/{vendorBill}', [\App\Http\Controllers\VendorBillController::class, 'show']);
            Route::middleware('role:admin,manager')->group(function () {
                Route::post('vendor-bills', [\App\Http\Controllers\VendorBillController::class, 'store']);
                Route::post('vendor-bills/{vendorBill}/approve', [\App\Http\Controllers\VendorBillController::class, 'approve']);
            });
        });

        // --- CRM: whole sales team works leads; deletion manager/admin ---
        Route::get('leads', [\App\Http\Controllers\LeadController::class, 'index']);
        Route::post('leads', [\App\Http\Controllers\LeadController::class, 'store']);
        Route::get('leads/{lead}', [\App\Http\Controllers\LeadController::class, 'show']);
        Route::match(['put', 'patch'], 'leads/{lead}', [\App\Http\Controllers\LeadController::class, 'update']);
        Route::post('leads/{lead}/activities', [\App\Http\Controllers\LeadController::class, 'addActivity']);
        Route::post('leads/{lead}/convert', [\App\Http\Controllers\LeadController::class, 'convert']);
        Route::delete('leads/{lead}', [\App\Http\Controllers\LeadController::class, 'destroy'])
            ->middleware('role:admin,manager');

        // --- sales: everyone reads & creates/confirms (employees sell) ---
        Route::get('sales', [SaleController::class, 'index']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);
        Route::delete('sales/{sale}', [SaleController::class, 'destroy']);
        Route::post('sales/{sale}/confirm', [SaleController::class, 'confirm']);
        Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel']);
        Route::match(['get', 'post'], 'sales/{sale}/invoice', [SaleController::class, 'invoice']);
        Route::post('sales/{sale}/email', [SaleController::class, 'email'])->middleware('throttle:20,1');

        // --- point of sale: cashiers (any authenticated) run the till ---
        Route::get('pos/session', [\App\Http\Controllers\PosController::class, 'session']);
        Route::post('pos/session/open', [\App\Http\Controllers\PosController::class, 'open']);
        Route::post('pos/session/{session}/close', [\App\Http\Controllers\PosController::class, 'close']);
        Route::get('pos/orders', [\App\Http\Controllers\PosController::class, 'index']);
        Route::post('pos/orders', [\App\Http\Controllers\PosController::class, 'checkout']);
        Route::get('pos/orders/{order}', [\App\Http\Controllers\PosController::class, 'show']);

        // --- pricelists & discounts: everyone reads and resolves prices;
        //     managers/admins manage the lists and rules ---
        Route::middleware('feature:pricelists')->group(function () {
            Route::get('pricing/resolve', [\App\Http\Controllers\PricelistController::class, 'resolve']);
            Route::get('pricelists', [\App\Http\Controllers\PricelistController::class, 'index']);
            Route::get('pricelists/{pricelist}', [\App\Http\Controllers\PricelistController::class, 'show']);
            Route::middleware('role:admin,manager')->group(function () {
                Route::post('pricelists', [\App\Http\Controllers\PricelistController::class, 'store']);
                Route::match(['put', 'patch'], 'pricelists/{pricelist}', [\App\Http\Controllers\PricelistController::class, 'update']);
                Route::delete('pricelists/{pricelist}', [\App\Http\Controllers\PricelistController::class, 'destroy']);
                Route::post('pricelists/{pricelist}/rules', [\App\Http\Controllers\PricelistController::class, 'addRule']);
                Route::delete('pricelist-rules/{rule}', [\App\Http\Controllers\PricelistController::class, 'removeRule']);
            });
        });

        // --- sales returns / credit notes: everyone reads; managers/admins issue ---
        Route::get('credit-notes', [\App\Http\Controllers\CreditNoteController::class, 'index']);
        Route::get('credit-notes/{creditNote}', [\App\Http\Controllers\CreditNoteController::class, 'show']);
        Route::get('sales/{sale}/returnable', [\App\Http\Controllers\CreditNoteController::class, 'returnable']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('sales/{sale}/credit-notes', [\App\Http\Controllers\CreditNoteController::class, 'store']);
        });

        // --- e-invoicing (TTN): everyone reads; managers/admins generate & submit ---
        Route::middleware('feature:einvoicing')->group(function () {
            Route::get('sales/{sale}/e-invoice', [\App\Http\Controllers\EInvoiceController::class, 'showForSale']);
            Route::get('e-invoices/{eInvoice}', [\App\Http\Controllers\EInvoiceController::class, 'show']);
            Route::middleware('role:admin,manager')->group(function () {
                Route::post('sales/{sale}/e-invoice', [\App\Http\Controllers\EInvoiceController::class, 'generate']);
                Route::post('e-invoices/{eInvoice}/submit', [\App\Http\Controllers\EInvoiceController::class, 'submit'])
                    ->middleware('throttle:30,1');
            });
        });

        // --- lot / batch & expiry tracking: everyone reads; managers/admins move ---
        Route::get('lots', [\App\Http\Controllers\LotController::class, 'index']);
        Route::get('lots/alerts', [\App\Http\Controllers\LotController::class, 'alerts']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('lots', [\App\Http\Controllers\LotController::class, 'store']);
            Route::post('lots/consume', [\App\Http\Controllers\LotController::class, 'consume']);
        });

        // --- reordering rules / auto-replenishment: managers/admins ---
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('reorder-rules', [\App\Http\Controllers\ReorderController::class, 'index']);
            Route::post('reorder-rules', [\App\Http\Controllers\ReorderController::class, 'store']);
            Route::match(['put', 'patch'], 'reorder-rules/{reorderRule}', [\App\Http\Controllers\ReorderController::class, 'update']);
            Route::delete('reorder-rules/{reorderRule}', [\App\Http\Controllers\ReorderController::class, 'destroy']);
            Route::get('reorder-suggestions', [\App\Http\Controllers\ReorderController::class, 'suggestions']);
            Route::post('reorder-run', [\App\Http\Controllers\ReorderController::class, 'run']);
        });

        // --- currencies & exchange rates: everyone reads/converts; admins configure ---
        Route::get('currencies', [\App\Http\Controllers\CurrencyController::class, 'index']);
        Route::get('currencies/convert', [\App\Http\Controllers\CurrencyController::class, 'convert']);
        Route::get('currencies/{currency}/rates', [\App\Http\Controllers\CurrencyController::class, 'rates']);
        Route::middleware('role:admin')->group(function () {
            Route::post('currencies', [\App\Http\Controllers\CurrencyController::class, 'store']);
            Route::match(['put', 'patch'], 'currencies/{currency}', [\App\Http\Controllers\CurrencyController::class, 'update']);
            Route::post('currencies/{currency}/rates', [\App\Http\Controllers\CurrencyController::class, 'setRate']);
        });

        // --- BI / report builder: managers/admins ---
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('bi/run', [\App\Http\Controllers\BiController::class, 'run']);
            Route::get('bi/reports', [\App\Http\Controllers\BiController::class, 'index']);
            Route::post('bi/reports', [\App\Http\Controllers\BiController::class, 'store']);
            Route::get('bi/reports/{report}/run', [\App\Http\Controllers\BiController::class, 'runSaved']);
            Route::delete('bi/reports/{report}', [\App\Http\Controllers\BiController::class, 'destroy']);
        });

        // --- shipping / delivery orders: everyone reads & creates; managers/admins move ---
        Route::get('shipments', [\App\Http\Controllers\ShipmentController::class, 'index']);
        Route::get('shipments/{shipment}', [\App\Http\Controllers\ShipmentController::class, 'show']);
        Route::post('shipments', [\App\Http\Controllers\ShipmentController::class, 'store']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('shipments/{shipment}/ship', [\App\Http\Controllers\ShipmentController::class, 'ship']);
            Route::post('shipments/{shipment}/deliver', [\App\Http\Controllers\ShipmentController::class, 'deliver']);
            Route::post('shipments/{shipment}/cancel', [\App\Http\Controllers\ShipmentController::class, 'cancel']);
        });

        // --- marketing campaigns (email/SMS, stubbed sender): managers/admins ---
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('campaigns', [\App\Http\Controllers\MarketingController::class, 'index']);
            Route::get('campaigns/{campaign}', [\App\Http\Controllers\MarketingController::class, 'show']);
            Route::post('campaigns', [\App\Http\Controllers\MarketingController::class, 'store']);
            Route::post('campaigns/{campaign}/send', [\App\Http\Controllers\MarketingController::class, 'send']);
        });

        // --- subscriptions / recurring billing: managers/admins ---
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'index']);
            Route::get('subscriptions/{subscription}', [\App\Http\Controllers\SubscriptionController::class, 'show']);
            Route::post('subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'store']);
            Route::post('subscriptions/run-billing', [\App\Http\Controllers\SubscriptionController::class, 'runBilling']);
            Route::post('subscriptions/{subscription}/status/{status}', [\App\Http\Controllers\SubscriptionController::class, 'setStatus'])
                ->whereIn('status', ['active', 'paused', 'cancelled']);
        });

        // --- helpdesk: support tickets. Anyone reads/creates/replies; managers assign & change status ---
        Route::get('tickets', [\App\Http\Controllers\HelpdeskController::class, 'index']);
        Route::get('tickets/{ticket}', [\App\Http\Controllers\HelpdeskController::class, 'show']);
        Route::post('tickets', [\App\Http\Controllers\HelpdeskController::class, 'store']);
        Route::post('tickets/{ticket}/reply', [\App\Http\Controllers\HelpdeskController::class, 'reply']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('tickets/{ticket}/assign', [\App\Http\Controllers\HelpdeskController::class, 'assign']);
            Route::post('tickets/{ticket}/status/{status}', [\App\Http\Controllers\HelpdeskController::class, 'transition'])
                ->whereIn('status', ['open', 'in_progress', 'resolved', 'closed']);
        });

        // --- procurement: RFQ & vendor bids. Managers/admins only ---
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('rfqs', [\App\Http\Controllers\RfqController::class, 'index']);
            Route::get('rfqs/{rfq}', [\App\Http\Controllers\RfqController::class, 'show']);
            Route::get('rfqs/{rfq}/comparison', [\App\Http\Controllers\RfqController::class, 'compare']);
            Route::post('rfqs', [\App\Http\Controllers\RfqController::class, 'store']);
            Route::post('rfqs/{rfq}/bids', [\App\Http\Controllers\RfqController::class, 'submitBid']);
            Route::post('rfqs/{rfq}/award/{bid}', [\App\Http\Controllers\RfqController::class, 'award']);
        });

        // --- projects & timesheets: everyone reads and logs their time; managers manage projects ---
        Route::get('projects', [\App\Http\Controllers\ProjectController::class, 'index']);
        Route::get('projects/{project}', [\App\Http\Controllers\ProjectController::class, 'show']);
        Route::get('projects/{project}/summary', [\App\Http\Controllers\ProjectController::class, 'summary']);
        Route::get('projects/{project}/timesheets', [\App\Http\Controllers\ProjectController::class, 'timesheets']);
        Route::post('projects/{project}/timesheets', [\App\Http\Controllers\ProjectController::class, 'logTime']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('projects', [\App\Http\Controllers\ProjectController::class, 'store']);
            Route::post('projects/{project}/close', [\App\Http\Controllers\ProjectController::class, 'close']);
            Route::post('projects/{project}/tasks', [\App\Http\Controllers\ProjectController::class, 'addTask']);
        });

        // --- manufacturing: BOMs & work orders. Everyone reads; managers/admins manage ---
        Route::get('boms', [\App\Http\Controllers\ManufacturingController::class, 'bomIndex']);
        Route::get('boms/{bom}', [\App\Http\Controllers\ManufacturingController::class, 'bomShow']);
        Route::get('work-orders', [\App\Http\Controllers\ManufacturingController::class, 'woIndex']);
        Route::get('work-orders/{workOrder}', [\App\Http\Controllers\ManufacturingController::class, 'woShow']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('boms', [\App\Http\Controllers\ManufacturingController::class, 'bomStore']);
            Route::post('work-orders', [\App\Http\Controllers\ManufacturingController::class, 'woStore']);
            Route::post('work-orders/{workOrder}/{action}', [\App\Http\Controllers\ManufacturingController::class, 'woAction'])
                ->whereIn('action', ['start', 'complete', 'cancel']);
        });

        // --- fixed assets & depreciation: everyone reads; managers/admins manage ---
        Route::get('assets', [\App\Http\Controllers\AssetController::class, 'index']);
        Route::get('assets/{asset}', [\App\Http\Controllers\AssetController::class, 'show']);
        Route::get('assets/{asset}/schedule', [\App\Http\Controllers\AssetController::class, 'schedule']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('assets', [\App\Http\Controllers\AssetController::class, 'store']);
            Route::post('assets/{asset}/depreciate', [\App\Http\Controllers\AssetController::class, 'depreciate']);
            Route::post('assets/{asset}/dispose', [\App\Http\Controllers\AssetController::class, 'dispose']);
        });

        // --- HR: attendance, leave, expense claims ---
        Route::prefix('hr')->group(function () {
            $hr = \App\Http\Controllers\HrController::class;
            // attendance & self-service creation: any authenticated employee
            Route::get('attendance', [$hr, 'attendance']);
            Route::post('attendance/clock-in', [$hr, 'clockIn']);
            Route::post('attendance/clock-out', [$hr, 'clockOut']);
            Route::get('leave', [$hr, 'leaveIndex']);
            Route::post('leave', [$hr, 'requestLeave']);
            Route::get('leave/balance', [$hr, 'leaveBalance']);
            Route::get('expenses', [$hr, 'expenseIndex']);
            Route::post('expenses', [$hr, 'submitClaim']);
            // approvals: managers/admins only
            Route::middleware('role:admin,manager')->group(function () use ($hr) {
                Route::post('leave/{leaveRequest}/{decision}', [$hr, 'decideLeave'])->whereIn('decision', ['approve', 'reject']);
                Route::post('expenses/{expenseClaim}/{decision}', [$hr, 'decideClaim'])->whereIn('decision', ['approve', 'reject', 'reimburse']);
            });
        });

        // --- accounting: everyone reads; managers/admins write ---
        Route::get('accounting/accounts', [\App\Http\Controllers\AccountingController::class, 'accounts']);
        Route::get('accounting/entries', [\App\Http\Controllers\AccountingController::class, 'entries']);
        Route::get('accounting/entries/{entry}', [\App\Http\Controllers\AccountingController::class, 'showEntry']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('accounting/accounts', [\App\Http\Controllers\AccountingController::class, 'storeAccount']);
            Route::post('accounting/entries', [\App\Http\Controllers\AccountingController::class, 'storeEntry']);
            Route::get('accounting/trial-balance', [\App\Http\Controllers\AccountingController::class, 'trialBalance']);
            Route::get('accounting/income-statement', [\App\Http\Controllers\AccountingController::class, 'incomeStatement']);
        });

        /*
        |--------------------------------------------------------------------
        | Tunisia localization layer
        |--------------------------------------------------------------------
        | Same RBAC shape as the rest of the API: everyone reads, managers and
        | admins write. Anything that posts to the ledger (instrument
        | lifecycle, payments, matching) is manager/admin only — an employee
        | can look up a cheque but cannot clear or bounce one.
        */

        // --- localization settings: everyone reads, admins configure ---
        Route::get('localization/profile', [LocalizationController::class, 'profile']);
        Route::get('localization/journals', [LocalizationController::class, 'journals']);
        Route::get('localization/mappings', [LocalizationController::class, 'mappings']);
        Route::middleware('role:admin')->group(function () {
            Route::match(['put', 'patch'], 'localization/profile', [LocalizationController::class, 'updateProfile']);
            Route::post('localization/journals', [LocalizationController::class, 'storeJournal']);
            Route::match(['put', 'patch'], 'localization/mappings', [LocalizationController::class, 'updateMappings']);
            Route::post('localization/chart-template', [LocalizationController::class, 'applyChartTemplate']);
        });

        // --- banks & bank accounts ---
        Route::get('banks', [BankController::class, 'banks']);
        Route::get('bank-accounts', [BankController::class, 'accounts']);
        Route::get('bank-accounts/{bankAccount}', [BankController::class, 'showAccount']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('banks', [BankController::class, 'storeBank']);
            Route::match(['put', 'patch'], 'banks/{bank}', [BankController::class, 'updateBank']);
            Route::post('bank-accounts', [BankController::class, 'storeAccount']);
            Route::match(['put', 'patch'], 'bank-accounts/{bankAccount}', [BankController::class, 'updateAccount']);
        });

        // --- bank statement lines ---
        Route::get('bank-transactions', [BankController::class, 'transactions']);
        Route::get('bank-transactions/{bankTransaction}', [BankController::class, 'showTransaction']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('bank-transactions', [BankController::class, 'storeTransaction']);
            Route::post('bank-transactions/import', [BankController::class, 'importTransactions']);
            Route::post('bank-transactions/preview', [BankController::class, 'previewImport']);
        });

        // --- reconciliation ---
        Route::get('reconciliation/report', [ReconciliationController::class, 'report'])
            ->middleware('role:admin,manager');
        Route::get('reconciliation/pending', [ReconciliationController::class, 'pending']);
        Route::get('reconciliation/{bankTransaction}/suggestions', [ReconciliationController::class, 'suggestions']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('reconciliation/{bankTransaction}/match', [ReconciliationController::class, 'match']);
            Route::post('reconciliation/{bankTransaction}/dispute', [ReconciliationController::class, 'dispute']);
            Route::delete('reconciliation/matches/{match}', [ReconciliationController::class, 'unmatch']);
        });

        // --- cheques & effets de commerce (kembyelet) ---
        Route::get('instruments', [InstrumentController::class, 'index']);
        Route::get('instruments/summary', [InstrumentController::class, 'summary']);
        Route::get('instruments/{instrument}', [InstrumentController::class, 'show']);
        Route::get('attachments/{attachment}', [InstrumentController::class, 'downloadAttachment']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('instruments', [InstrumentController::class, 'store']);
            Route::match(['put', 'patch'], 'instruments/{instrument}', [InstrumentController::class, 'update']);
            Route::post('instruments/{instrument}/receive', [InstrumentController::class, 'receive']);
            Route::post('instruments/{instrument}/issue', [InstrumentController::class, 'issue']);
            Route::post('instruments/{instrument}/deposit', [InstrumentController::class, 'deposit']);
            Route::post('instruments/{instrument}/pending', [InstrumentController::class, 'markPending']);
            Route::post('instruments/{instrument}/clear', [InstrumentController::class, 'clear']);
            Route::post('instruments/{instrument}/bounce', [InstrumentController::class, 'bounce']);
            Route::post('instruments/{instrument}/settle', [InstrumentController::class, 'settle']);
            Route::post('instruments/{instrument}/cancel', [InstrumentController::class, 'cancel']);
            Route::post('instruments/{instrument}/attachments', [InstrumentController::class, 'attach']);
        });

        // --- installment plans ("khlas bel taqsit") ---
        Route::get('installment-plans', [InstallmentController::class, 'index']);
        Route::get('installment-plans/{plan}', [InstallmentController::class, 'show']);
        Route::get('installment-plans/{plan}/history', [InstallmentController::class, 'history']);
        Route::get('installments/overdue', [InstallmentController::class, 'overdue']);
        Route::get('customers/{customerId}/credit', [InstallmentController::class, 'customerCredit'])
            ->whereNumber('customerId');
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('installment-plans', [InstallmentController::class, 'store']);
            Route::post('installment-plans/{plan}/cancel', [InstallmentController::class, 'cancel']);
            Route::post('installments/{installment}/pay', [InstallmentController::class, 'pay']);
            Route::post('installments/refresh-overdue', [InstallmentController::class, 'refreshOverdue']);
        });

        // --- payments ---
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/summary', [PaymentController::class, 'summary']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
        Route::post('payments', [PaymentController::class, 'store'])->middleware('role:admin,manager');
        // Foreign-currency settlement with realized FX gain/loss.
        Route::post('payments/settle-foreign', [PaymentController::class, 'settleForeign'])
            ->middleware(['feature:foreign_currency', 'role:admin,manager']);

        // --- treasury dashboard ---
        Route::get('dashboard/treasury', [TreasuryController::class, 'dashboard']);

        /*
        |--------------------------------------------------------------------
        | Notifications — behind the notifications feature flag.
        | Everyone reads and manages their OWN; a scan (which generates the
        | time-based ones) is a manager/admin action.
        */
        Route::middleware('feature:notifications')->group(function () {
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
            Route::post('notifications/scan', [NotificationController::class, 'scan'])
                ->middleware('role:admin,manager');
        });

        /*
        |--------------------------------------------------------------------
        | Payroll ("gestion de paie") — behind the payroll feature flag.
        | Everyone with access reads; managers/admins run payroll and pay
        | advances, since both post to the ledger.
        */
        Route::middleware('feature:payroll')->group(function () {
            Route::get('employees', [PayrollController::class, 'employees']);
            Route::get('employees/{employee}', [PayrollController::class, 'showEmployee']);
            Route::get('advances', [PayrollController::class, 'advances']);
            Route::get('payroll/runs', [PayrollController::class, 'runs']);
            Route::get('payroll/runs/{run}', [PayrollController::class, 'showRun']);
            Route::get('payroll/settings', [PayrollController::class, 'settings']);

            Route::middleware('role:admin,manager')->group(function () {
                Route::post('employees', [PayrollController::class, 'storeEmployee']);
                Route::match(['put', 'patch'], 'payroll/settings', [PayrollController::class, 'updateSettings']);
                Route::match(['put', 'patch'], 'employees/{employee}', [PayrollController::class, 'updateEmployee']);

                Route::post('advances', [PayrollController::class, 'requestAdvance']);
                Route::post('advances/{advance}/pay', [PayrollController::class, 'payAdvance']);
                Route::post('advances/{advance}/cancel', [PayrollController::class, 'cancelAdvance']);

                Route::post('payroll/runs', [PayrollController::class, 'createRun']);
                Route::post('payroll/payslips/{payslip}/lines', [PayrollController::class, 'addLine']);
                Route::delete('payroll/lines/{line}', [PayrollController::class, 'removeLine']);
                Route::post('payroll/runs/{run}/approve', [PayrollController::class, 'approveRun']);
                Route::post('payroll/runs/{run}/pay', [PayrollController::class, 'payRun']);
            });
        });

        // --- owner's profit view (managers/admins) ---
        Route::get('owner/profit', [OwnerController::class, 'profit'])
            ->middleware('role:admin,manager');

        // --- dashboard & reports ---
        Route::get('dashboard/stats', [ReportingController::class, 'dashboard']);
        Route::get('dashboard/forecast', [ReportingController::class, 'forecast']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('reports/sales', [ReportingController::class, 'salesReport']);
            Route::get('reports/purchases', [ReportingController::class, 'purchasesReport']);
            Route::get('reports/stock', [ReportingController::class, 'stockReport']);
            Route::get('reports/vat', [ReportingController::class, 'vatReturn']);
        });

        // --- AR dunning: overdue-invoice follow-ups, managers/admins, behind the flag ---
        Route::middleware(['feature:dunning', 'role:admin,manager'])->group(function () {
            Route::get('dunning/levels', [\App\Http\Controllers\DunningController::class, 'levels']);
            Route::get('dunning/candidates', [\App\Http\Controllers\DunningController::class, 'candidates']);
            Route::post('dunning/run', [\App\Http\Controllers\DunningController::class, 'run'])
                ->middleware('throttle:10,1');
        });

        // --- analytic accounting (cost centres): managers/admins, behind the flag ---
        Route::middleware(['feature:analytic', 'role:admin,manager'])->group(function () {
            Route::get('reports/analytic', [\App\Http\Controllers\AnalyticController::class, 'pnl']);
            Route::post('journal-lines/{line}/analytic', [\App\Http\Controllers\AnalyticController::class, 'tagLine']);
        });

        // --- budgets & budget-vs-actual: managers/admins, behind the flag ---
        Route::middleware(['feature:budgets', 'role:admin,manager'])->group(function () {
            Route::get('budgets', [\App\Http\Controllers\BudgetController::class, 'index']);
            Route::post('budgets', [\App\Http\Controllers\BudgetController::class, 'store']);
            Route::get('budgets/{budget}', [\App\Http\Controllers\BudgetController::class, 'show']);
            Route::get('budgets/{budget}/vs-actual', [\App\Http\Controllers\BudgetController::class, 'vsActual']);
            Route::post('budgets/{budget}/lines', [\App\Http\Controllers\BudgetController::class, 'upsertLine']);
            Route::delete('budgets/{budget}', [\App\Http\Controllers\BudgetController::class, 'destroy']);
        });

        // --- OCR: invoice image → structured data (managers/admins) ---
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('ocr/invoice', [\App\Http\Controllers\OcrController::class, 'invoice']);
        });

        // --- chatter: comments & follow-up activities on any record ---
        Route::middleware('feature:chatter')->group(function () {
            Route::get('activities/mine', [\App\Http\Controllers\ChatterController::class, 'mine']);
            Route::post('activities/{activity}/toggle', [\App\Http\Controllers\ChatterController::class, 'toggleActivity']);
            Route::get('chatter/{type}/{id}', [\App\Http\Controllers\ChatterController::class, 'index'])->whereNumber('id');
            Route::post('chatter/{type}/{id}/messages', [\App\Http\Controllers\ChatterController::class, 'storeMessage'])->whereNumber('id');
            Route::post('chatter/{type}/{id}/activities', [\App\Http\Controllers\ChatterController::class, 'storeActivity'])->whereNumber('id');
        });

        // --- documents (RAG): all read/search; managers/admins manage ---
        Route::get('documents', [\App\Http\Controllers\DocumentController::class, 'index']);
        Route::get('documents/search', [\App\Http\Controllers\DocumentController::class, 'search']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('documents', [\App\Http\Controllers\DocumentController::class, 'store']);
            Route::delete('documents/{title}', [\App\Http\Controllers\DocumentController::class, 'destroy']);
        });

        // --- AI assistant ---
        Route::post('agent/chat', [AssistantController::class, 'chat'])->middleware('throttle:30,1');
        Route::get('agent/conversations', [AssistantController::class, 'conversations']);
        Route::delete('agent/conversations/{id}', [AssistantController::class, 'destroyConversation']);

        /*
        |--------------------------------------------------------------------
        | Administration (Phase 1) — admin only
        |--------------------------------------------------------------------
        | Organisation structure, the permission engine, the audit trail and
        | feature flags. `me/context` is the one exception: every signed-in
        | user needs their own effective permissions and the enabled modules
        | in order to render the correct UI.
        */
        Route::get('me/context', function (Request $request) {
            // A custom role narrows what the holder may see to its module
            // allowlist; built-in roles resolve to null (no restriction).
            $role = \App\Models\Role::where('key', $request->user()->role)->first();
            $modules = $role?->moduleAllowlist();

            return response()->json([
                'permissions' => \App\Services\PermissionService::keysFor($request->user()),
                'features' => \App\Models\FeatureFlag::map(),
                'modules' => $modules,   // null = unrestricted, else allowlist
                'company' => \App\Models\Company::current()?->toApi(),
            ]);
        });

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            // organisation
            Route::get('companies', [AdministrationController::class, 'companies']);
            Route::post('companies', [AdministrationController::class, 'storeCompany']);
            Route::match(['put', 'patch'], 'companies/{company}', [AdministrationController::class, 'updateCompany']);
            Route::get('branches', [AdministrationController::class, 'branches']);
            Route::post('branches', [AdministrationController::class, 'storeBranch']);
            Route::get('business-units', [AdministrationController::class, 'businessUnits']);
            Route::post('business-units', [AdministrationController::class, 'storeBusinessUnit']);

            // fiscal years & numbering
            Route::get('fiscal-years', [AdministrationController::class, 'fiscalYears']);
            Route::post('fiscal-years', [AdministrationController::class, 'storeFiscalYear']);
            Route::match(['put', 'patch'], 'fiscal-years/{fiscalYear}', [AdministrationController::class, 'updateFiscalYearStatus']);
            Route::get('sequences', [AdministrationController::class, 'sequences']);
            Route::match(['put', 'patch'], 'sequences/{sequence}', [AdministrationController::class, 'updateSequence']);

            // permissions
            Route::get('permissions', [AdministrationController::class, 'permissions']);
            Route::get('roles', [AdministrationController::class, 'roles']);
            Route::post('roles', [AdministrationController::class, 'storeRole']);
            Route::match(['put', 'patch'], 'roles/{role}/permissions', [AdministrationController::class, 'updateRolePermissions']);
            Route::delete('roles/{role}', [AdministrationController::class, 'destroyRole']);
            Route::get('users/{user}/permissions', [AdministrationController::class, 'userPermissions']);
            Route::post('users/{user}/permissions', [AdministrationController::class, 'grantPermission']);
            Route::delete('users/{user}/permissions', [AdministrationController::class, 'revokePermission']);
            Route::post('field-permissions', [AdministrationController::class, 'storeFieldPermission']);
            Route::get('permission-audit', [AdministrationController::class, 'permissionAudit']);

            // audit trail
            Route::get('audit', [AdministrationController::class, 'audit']);
            Route::get('audit/export', [AdministrationController::class, 'exportAudit']);

            // feature flags
            Route::get('features', [AdministrationController::class, 'features']);
            Route::match(['put', 'patch'], 'features/{feature}', [AdministrationController::class, 'updateFeature']);
        });

        // Activity timeline for a single record — any authenticated user may
        // read the history of something they can already see.
        Route::get('timeline/{type}/{id}', [AdministrationController::class, 'timeline'])
            ->whereNumber('id');

        // --- users: admin only ---
        Route::middleware('role:admin')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::get('users/{user}', [UserController::class, 'show']);
            Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update']);
            Route::delete('users/{user}', [UserController::class, 'destroy']);
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        });

        // --- custom roles & their module access ---
        // Admins may read the list (the users screen assigns from it); only a
        // super admin may create, edit or delete a custom role.
        Route::middleware('role:admin')->group(function () {
            Route::get('roles', [\App\Http\Controllers\RoleController::class, 'index']);
            Route::get('roles/modules', [\App\Http\Controllers\RoleController::class, 'modules']);
        });
        Route::middleware('role:super_admin')->group(function () {
            Route::post('roles', [\App\Http\Controllers\RoleController::class, 'store']);
            Route::match(['put', 'patch'], 'roles/{role}', [\App\Http\Controllers\RoleController::class, 'update']);
            Route::delete('roles/{role}', [\App\Http\Controllers\RoleController::class, 'destroy']);
        });
    });
});
