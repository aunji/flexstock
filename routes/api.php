<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Auth Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

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
| Tenant Routes (Multi-tenant with company prefix)
|--------------------------------------------------------------------------
*/
Route::prefix('{company}')
    ->middleware(['auth:sanctum', 'tenant'])
    ->group(function () {
        // Products
        Route::apiResource('products', ProductController::class);
        Route::post('products/{product}/adjust-stock', [ProductController::class, 'adjustStock']);

        // Sale Orders
        Route::apiResource('sale-orders', SaleOrderController::class)->except(['update', 'destroy']);
        Route::post('sale-orders/{saleOrder}/confirm', [SaleOrderController::class, 'confirm']);
        Route::post('sale-orders/{saleOrder}/cancel', [SaleOrderController::class, 'cancel']);
        Route::post('sale-orders/{saleOrder}/mark-payment-received', [SaleOrderController::class, 'markPaymentReceived']);

        // Reports
        Route::get('reports/sales-summary', [ReportController::class, 'salesSummary']);
        Route::get('reports/top-products', [ReportController::class, 'topProducts']);
        Route::get('reports/low-stock', [ReportController::class, 'lowStock']);
        Route::get('reports/daily-sales', [ReportController::class, 'dailySales']);
    });
