<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.dashboard')->name('dashboard');

Route::middleware(['auth', 'permission:pos.access'])->group(function () {
    Route::view('/pos', 'pages.pos')->name('pos');
});

Route::middleware(['auth', 'permission:inventory.view'])->group(function () {
    Route::view('/inventory', 'pages.inventory')->name('inventory');
});

require __DIR__.'/auth.php';
