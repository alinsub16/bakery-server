<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BreadController;
use App\Http\Controllers\Api\V1\BreadOpeningBalanceController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DailyInventoryController;
use App\Http\Controllers\Api\V1\DailyProductionController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProductionVarianceController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\SalesReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // ---- Admin: reads (roles, users, pending queue) ----
        Route::middleware('role:admin')->group(function () {
            Route::get('/roles', [RoleController::class, 'index']);
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/pending', [UserController::class, 'pending']);
        });

        // ---- Admin: writes (user/role management) ----
        Route::middleware(['role:admin', 'throttle:api-writes'])->group(function () {
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{user}/role', [UserController::class, 'updateRole']);
            Route::patch('/users/{user}/approve', [UserController::class, 'approve']);
            Route::patch('/users/{user}/reject', [UserController::class, 'reject']);
            Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate']);
            Route::patch('/users/{id}/activate', [UserController::class, 'activate']);
        });

        // ---- Admin + Manager: audit visibility ----
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        });

        // ---- Readable by any authenticated role ----
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);
        Route::get('/breads', [BreadController::class, 'index']);
        Route::get('/breads/{bread}', [BreadController::class, 'show']);
        Route::get('/breads/{bread}/opening-balance', [BreadOpeningBalanceController::class, 'show']);
        Route::get('/production', [DailyProductionController::class, 'index']);
        Route::get('/inventory', [DailyInventoryController::class, 'index']);
        Route::get('/inventory/opening-stock/{bread}', [DailyInventoryController::class, 'openingStock']);

        Route::get('/sales/daily-summary', [SalesReportController::class, 'dailySummary']);
        Route::get('/sales/range', [SalesReportController::class, 'range']);
        Route::get('/sales/by-bread', [SalesReportController::class, 'byBread']);
        Route::get('/sales/monthly', [SalesReportController::class, 'monthly']);
        Route::get('/sales/yearly', [SalesReportController::class, 'yearly']);

        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        Route::get('/reports/production-variance', [ProductionVarianceController::class, 'index']);

        // ---- Admin + Manager: mutations on categories, breads, corrections, opening balance ----
        Route::middleware(['role:admin,manager', 'throttle:api-writes'])->group(function () {
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{category}', [CategoryController::class, 'update']);
            Route::patch('/categories/{category}/deactivate', [CategoryController::class, 'deactivate']);
            Route::patch('/categories/{category}/activate', [CategoryController::class, 'activate']);

            Route::post('/breads', [BreadController::class, 'store']);
            Route::put('/breads/{bread}', [BreadController::class, 'update']);
            Route::patch('/breads/{bread}/deactivate', [BreadController::class, 'deactivate']);
            Route::patch('/breads/{bread}/activate', [BreadController::class, 'activate']);

            Route::put('/production/{production}', [DailyProductionController::class, 'update']);
            Route::put('/inventory/{inventory}', [DailyInventoryController::class, 'update']);

            Route::post('/breads/{bread}/opening-balance', [BreadOpeningBalanceController::class, 'store']);
        });

        // ---- Production submission: admin, manager, baker, inventory_clerk ----
        Route::middleware(['role:admin,manager,baker,inventory_clerk', 'throttle:api-writes'])->group(function () {
            Route::post('/production', [DailyProductionController::class, 'store']);
        });

        // ---- Inventory (closing stock) submission: admin, manager, inventory_clerk ----
        Route::middleware(['role:admin,manager,inventory_clerk', 'throttle:api-writes'])->group(function () {
            Route::post('/inventory', [DailyInventoryController::class, 'store']);
        });
    });
});