<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('login', 'pages.auth.login')->name('login');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', function (Request $request) {
        $user = Auth::user();
        $isFloorStaff = session('auth_method') === 'pin' || ($user && $user->isStrictFloorStaff());

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($isFloorStaff) {
            return redirect()->route('login');
        }

        return redirect('/admin/login');
    })->name('logout');
});
