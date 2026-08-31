<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.dashboard');
Route::view('/pos', 'pages.pos');
Route::view('/inventory', 'pages.inventory');
