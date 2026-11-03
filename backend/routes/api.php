<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\KitchenTicketController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TableController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public Auth Endpoints
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/pin-login', [AuthController::class, 'pinLogin']);

    // Authenticated Endpoints
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Catalog Read (All authenticated POS staff)
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::get('/modifier-groups', [ProductController::class, 'modifierGroups']);

        // Catalog Write (Requires catalog.manage permission or owner/manager role)
        Route::middleware('role_or_permission:owner|manager|catalog.manage')->group(function (): void {
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{product}', [ProductController::class, 'update']);
            Route::patch('/products/{product}/availability', [ProductController::class, 'updateAvailability']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        });

        // Tables & Dine-In
        Route::get('/areas', [TableController::class, 'areas']);
        Route::post('/areas', [TableController::class, 'storeArea']);
        Route::get('/tables', [TableController::class, 'index']);
        Route::post('/tables', [TableController::class, 'store']);
        Route::put('/tables/{table}', [TableController::class, 'update']);
        Route::post('/tables/{table}/move', [TableController::class, 'move']);
        Route::post('/tables/merge', [TableController::class, 'merge']);

        // Shifts & Cash
        Route::get('/shifts/current', [ShiftController::class, 'current']);
        Route::post('/shifts/open', [ShiftController::class, 'open']);
        Route::post('/shifts/close', [ShiftController::class, 'close']);
        Route::post('/shifts/{shift}/cash-movements', [ShiftController::class, 'storeCashMovement']);

        // Orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/{order}/items', [OrderController::class, 'addItems']);
        Route::post('/orders/{order}/hold', [OrderController::class, 'hold']);
        Route::post('/orders/{order}/void-item', [OrderController::class, 'voidItem']);
        Route::post('/orders/{order}/split', [OrderController::class, 'split']);

        // Payments & Refunds
        Route::get('/payment-methods', [PaymentController::class, 'methods']);
        Route::post('/orders/{order}/payments', [PaymentController::class, 'store']);
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);

        // Kitchen & Bar Tickets
        Route::get('/kitchen/tickets', [KitchenTicketController::class, 'index']);
        Route::patch('/kitchen/tickets/{ticket}/status', [KitchenTicketController::class, 'updateStatus']);

        // Offline Sync (Idempotent via client_uuid)
        Route::post('/sync/batch', [SyncController::class, 'batch']);

        // Reports
        Route::middleware('role_or_permission:owner|manager|report.view')->group(function (): void {
            Route::get('/reports/sales', [ReportController::class, 'sales']);
            Route::get('/reports/products', [ReportController::class, 'products']);
            Route::get('/reports/shifts', [ReportController::class, 'shifts']);
            Route::get('/reports/voids', [ReportController::class, 'voids']);
        });

        // Settings & Printers
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);
        Route::get('/printers', [SettingController::class, 'printers']);
        Route::post('/printers', [SettingController::class, 'storePrinter']);
    });
});
