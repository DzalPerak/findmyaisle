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

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
