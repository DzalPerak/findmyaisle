<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\WaypointController;

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

Route::get('shoppinglist', function () {
    return Inertia::render('ShoppingLists');
})->middleware(['auth', 'verified'])->name('shoppinglists');

// API Routes - Authenticated users
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    // Route Planning API - Must come before parameterized routes
    Route::get('/shops/available', [ShopController::class, 'getAvailableShops'])->name('api.shops.available');
    Route::get('/shops/{shop}/dxf', [ShopController::class, 'getDxfFile'])->name('api.shops.dxf');
    
    // Shop details - Available to all authenticated users for pathfinding
    Route::get('/shops/{shop}', [ShopController::class, 'show'])->name('api.shops.show');
    
    // Shop Management API Routes - Admin/Editor only
    Route::middleware('role:admin,editor')->group(function () {
        Route::get('/shops', [ShopController::class, 'index'])->name('api.shops.index');
        Route::post('/shops', [ShopController::class, 'store'])->name('api.shops.store');
        Route::put('/shops/{shop}', [ShopController::class, 'update'])->name('api.shops.update');
        Route::put('/shops/{shop}/dxf', [ShopController::class, 'updateDxf'])->name('api.shops.update-dxf');
        Route::get('/shops/{shop}/layout', [ShopController::class, 'getLayout'])->name('api.shops.get-layout');
        Route::post('/shops/{shop}/layout', [ShopController::class, 'saveLayout'])->name('api.shops.save-layout');
        Route::delete('/shops/{shop}', [ShopController::class, 'destroy'])->name('api.shops.destroy');
    });
});

// Product and Shopping List API Routes
Route::get('/api/products', [ProductController::class, 'index'])->middleware(['auth', 'verified']);

Route::get('/api/shopping-lists', [ShoppingListController::class, 'index'])->middleware(['auth', 'verified']);
Route::post('/api/shopping-lists', [ShoppingListController::class, 'store'])->middleware(['auth', 'verified']);
Route::put('/api/shopping-lists/{shoppingList}', [ShoppingListController::class, 'update'])->middleware(['auth', 'verified']);
Route::delete('/api/shopping-lists/{shoppingList}', [ShoppingListController::class, 'destroy'])->middleware(['auth', 'verified']);
Route::delete('/api/shopping-lists/{shoppingList}/items', [ShoppingListController::class, 'removeItem'])->middleware(['auth', 'verified']);

// Waypoint and Category API Routes
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    // Categories (admin/editor only)
    Route::get('/categories', [WaypointController::class, 'getCategories'])->middleware('role:admin,editor');
    
    // Waypoint categories management (admin/editor only) 
    Route::get('/waypoints/{waypoint}', [WaypointController::class, 'show'])->middleware('role:admin,editor');
    Route::put('/waypoints/{waypoint}/categories', [WaypointController::class, 'updateCategories'])->middleware('role:admin,editor');
    
    // Waypoint selection by shopping list (all authenticated users)
    Route::post('/waypoints/by-shopping-list', [WaypointController::class, 'getWaypointsByShoppingList']);
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
