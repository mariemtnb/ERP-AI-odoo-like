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
use App\Http\Controllers\PaymentController;
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

    /*
    | `active` runs on every authenticated request: deactivating an account has
    | to revoke access immediately, not whenever the current token happens to
    | expire. `throttle:api` is a blanket ceiling so no endpoint is unbounded.
    */
    Route::middleware(['auth:api', 'active', 'throttle:api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
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

        // --- treasury dashboard ---
        Route::get('dashboard/treasury', [TreasuryController::class, 'dashboard']);

        // --- dashboard & reports ---
        Route::get('dashboard/stats', [ReportingController::class, 'dashboard']);
        Route::get('dashboard/forecast', [ReportingController::class, 'forecast']);
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('reports/sales', [ReportingController::class, 'salesReport']);
            Route::get('reports/purchases', [ReportingController::class, 'purchasesReport']);
            Route::get('reports/stock', [ReportingController::class, 'stockReport']);
        });

        // --- OCR: invoice image → structured data (managers/admins) ---
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('ocr/invoice', [\App\Http\Controllers\OcrController::class, 'invoice']);
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
            return response()->json([
                'permissions' => \App\Services\PermissionService::keysFor($request->user()),
                'features' => \App\Models\FeatureFlag::map(),
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
    });
});
