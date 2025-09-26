<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('navi', function () {
    return Inertia::render('Navi');
})->middleware(['auth', 'verified'])->name('navi');

Route::get('shoppinglist', function () {
    return Inertia::render('ShoppingLists');
})->middleware(['auth', 'verified'])->name('shoppinglists');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShoppingListController;
Route::get('/api/products', [ProductController::class, 'index'])->middleware(['auth', 'verified']);

Route::get('/api/shopping-list', [ShoppingListController::class, 'index'])->middleware(['auth', 'verified']);
Route::post('/api/shopping-list', [ShoppingListController::class, 'store'])->middleware(['auth', 'verified']);
Route::delete('/api/shopping-list/{id}', [ShoppingListController::class, 'destroy'])->middleware(['auth', 'verified']);
