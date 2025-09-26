<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ShopController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('navi', function () {
    return Inertia::render('Navi');
})->middleware(['auth', 'verified'])->name('navi');

Route::get('plan-route', function () {
    return Inertia::render('PlanRoute');
})->middleware(['auth', 'verified'])->name('plan-route');

Route::get('shops', function () {
    return Inertia::render('ShopSelection');
})->middleware(['auth', 'verified'])->name('shops');

Route::get('pathfinder', function () {
    return Inertia::render('Pathfinder');
})->middleware(['auth', 'verified'])->name('pathfinder');

// API Routes - Authenticated users
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    // Route Planning API - Must come before parameterized routes
    Route::get('/shops/available', [ShopController::class, 'getAvailableShops'])->name('api.shops.available');
    Route::get('/shops/{shop}/dxf', [ShopController::class, 'getDxfFile'])->name('api.shops.dxf');
    
    // Shop Management API Routes - Admin/Editor only
    Route::middleware('role:admin,editor')->group(function () {
        Route::get('/shops', [ShopController::class, 'index'])->name('api.shops.index');
        Route::post('/shops', [ShopController::class, 'store'])->name('api.shops.store');
        Route::get('/shops/{shop}', [ShopController::class, 'show'])->name('api.shops.show');
        Route::put('/shops/{shop}', [ShopController::class, 'update'])->name('api.shops.update');
        Route::delete('/shops/{shop}', [ShopController::class, 'destroy'])->name('api.shops.destroy');
    });
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
