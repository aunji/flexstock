<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\PaymentSlipController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleOrderController;
use App\Http\Controllers\Api\StockController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Auth Routes (with strict rate limiting)
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| Tenant Routes (Multi-tenant with company prefix + RBAC)
|--------------------------------------------------------------------------
*/
Route::prefix('{company}')
    ->middleware(['auth:sanctum', 'tenant'])
    ->group(function () {
        // Products - Admin/Cashier can write, all can read
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::middleware(['role:admin,cashier'])->group(function () {
            Route::post('products', [ProductController::class, 'store']);
            Route::put('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);
        });

        // Sale Orders - Cashier+ can create, Admin can approve/cancel
        Route::get('sale-orders', [SaleOrderController::class, 'index']);
        Route::get('sale-orders/{saleOrder}', [SaleOrderController::class, 'show']);
        Route::middleware(['role:admin,cashier', 'throttle:financial'])->group(function () {
            Route::post('sale-orders', [SaleOrderController::class, 'store']);
            Route::post('sale-orders/{saleOrder}/confirm', [SaleOrderController::class, 'confirm']);
            Route::post('sale-orders/{saleOrder}/mark-payment-received', [SaleOrderController::class, 'markPaymentReceived']);
        });
        Route::middleware(['role:admin', 'throttle:financial'])->group(function () {
            Route::post('sale-orders/{saleOrder}/cancel', [SaleOrderController::class, 'cancel']);
        });

        // Stock Management - Admin only for adjustments
        Route::middleware(['role:admin'])->group(function () {
            Route::post('stock/adjust', [StockController::class, 'adjust']);
        });
        Route::get('stock/movements', [StockController::class, 'movements']);
        Route::get('stock/low-stock', [StockController::class, 'lowStock']);
        Route::get('stock/out-of-stock', [StockController::class, 'outOfStock']);

        // Payment Slips - Cashier can upload, Admin can approve
        Route::middleware(['throttle:financial'])->group(function () {
            Route::get('payment-slips', [PaymentSlipController::class, 'index']);
            Route::get('payment-slips/{id}', [PaymentSlipController::class, 'show']);
            Route::middleware(['role:admin,cashier'])->group(function () {
                Route::post('payment-slips', [PaymentSlipController::class, 'upload']);
            });
            Route::middleware(['role:admin'])->group(function () {
                Route::post('payment-slips/{id}/approve', [PaymentSlipController::class, 'approve']);
                Route::post('payment-slips/{id}/reject', [PaymentSlipController::class, 'reject']);
                Route::delete('payment-slips/{id}', [PaymentSlipController::class, 'destroy']);
            });
        });

        // Custom Fields - Admin only
        Route::middleware(['role:admin', 'throttle:admin'])->group(function () {
            Route::get('custom-fields', [CustomFieldController::class, 'index']);
            Route::get('custom-fields/schema', [CustomFieldController::class, 'schema']);
            Route::post('custom-fields', [CustomFieldController::class, 'store']);
            Route::get('custom-fields/{id}', [CustomFieldController::class, 'show']);
            Route::put('custom-fields/{id}', [CustomFieldController::class, 'update']);
            Route::delete('custom-fields/{id}', [CustomFieldController::class, 'destroy']);
        });

        // Reports - All authenticated users
        Route::get('reports/sales-summary', [ReportController::class, 'salesSummary']);
        Route::get('reports/top-products', [ReportController::class, 'topProducts']);
        Route::get('reports/low-stock', [ReportController::class, 'lowStock']);
        Route::get('reports/daily-sales', [ReportController::class, 'dailySales']);
    });
