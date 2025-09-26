<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ShopController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here are the admin routes that require specific role permissions.
| These routes are protected by the 'role' middleware.
|
*/

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    
    // Admin Dashboard - accessible by admin and editor
    Route::get('/', function () {
        return Inertia::render('admin/MainAdminMenu');
    })->middleware('role:admin,editor')->name('admin');

    // Shops Management - accessible by admin and editor
    Route::get('/shops', function () {
        return Inertia::render('admin/Shops');
    })->middleware('role:admin,editor')->name('admin.shops');

    // Map Editor - accessible by admin and editor  
    Route::get('/map-editor', function () {
        return Inertia::render('admin/MapEditor');
    })->middleware('role:admin,editor')->name('admin.map-editor');

    // Analytics - accessible by admin and editor
    Route::get('/analytics', function () {
        return Inertia::render('admin/Analytics');
    })->middleware('role:admin,editor')->name('admin.analytics');

    // Roles Management - admin only
    Route::get('/roles', function () {
        return Inertia::render('admin/Roles');
    })->middleware('role:admin')->name('admin.roles');

    // Users Management - admin only
    Route::get('/users', function () {
        return Inertia::render('admin/Users');
    })->middleware('role:admin')->name('admin.users');

    // System Settings - admin only
    Route::get('/settings', function () {
        return Inertia::render('admin/Settings');
    })->middleware('role:admin')->name('admin.settings');
});