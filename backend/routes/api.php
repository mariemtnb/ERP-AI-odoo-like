<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
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
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::middleware('auth:api')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        // --- catalog & stock: everyone reads, managers/admins write ---
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{category}', [CategoryController::class, 'show']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::get('stock/movements', [StockMovementController::class, 'index']);
        Route::get('stock/movements/{movement}', [StockMovementController::class, 'show']);

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

        // --- sales: everyone reads & creates/confirms (employees sell) ---
        Route::get('sales', [SaleController::class, 'index']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);
        Route::delete('sales/{sale}', [SaleController::class, 'destroy']);
        Route::post('sales/{sale}/confirm', [SaleController::class, 'confirm']);
        Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel']);
        Route::match(['get', 'post'], 'sales/{sale}/invoice', [SaleController::class, 'invoice']);

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
