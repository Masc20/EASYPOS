<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check() && auth()->user()->isStrictFloorStaff()) {
        return redirect()->to(auth()->user()->stationRoute());
    }

    return view('pages.dashboard');
})->name('dashboard');

Route::middleware(['auth', 'permission:pos.access'])->group(function () {
    Route::view('/pos', 'pages.pos')->name('pos');
});

Route::middleware(['auth', 'permission:inventory.view'])->group(function () {
    Route::view('/inventory', 'pages.inventory')->name('inventory');
});

Route::middleware(['auth', 'permission:kitchen.view'])->group(function () {
    Route::view('/kitchen', 'pages.kitchen')->name('kitchen');
});

require __DIR__.'/auth.php';
