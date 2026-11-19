<?php

use App\Domains\Identity\Events\UserLoggedOut;
use App\Support\Constants\SystemConstants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('login', 'pages.auth.login')->name('login');
});

Route::post('logout', function (Request $request) {
    $user = Auth::guard('web')->user();
    $isFloorStaff = $request->hasSession()
        ? ($request->session()->get(SystemConstants::SESSION_AUTH_METHOD) === 'pin'
            || ($user && $user->isStrictFloorStaff()))
        : true;

    if ($user) {
        Auth::guard('web')->logout();
        event(new UserLoggedOut($user));
    }

    if ($request->hasSession()) {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    if (! $user) {
        $referer = (string) $request->headers->get('Referer', '');

        return str_contains($referer, '/admin')
            ? redirect('/admin/login')
            : redirect()->route('login');
    }

    return $isFloorStaff
        ? redirect()->route('login')
        : redirect('/admin/login');
})->name('logout');
