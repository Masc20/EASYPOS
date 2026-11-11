<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        if (! auth()->user()->is_active) {
            auth()->logout();
            session()->invalidate();
            session()->regenerateToken();

            return redirect()->route('login')->withErrors(['emp_id' => 'Your account has been deactivated. Please contact your manager or system administrator.']);
        }

        if (auth()->user()->isStrictFloorStaff()) {
            return redirect()->to(auth()->user()->stationRoute());
        }
    }

    return view('pages.dashboard');
})->name('dashboard');

Route::middleware(['auth', 'permission:pos.access'])->group(function () {
    Route::view('/pos', 'pages.pos')->name('pos');
});

Route::middleware(['auth', 'permission:menu.view'])->group(function () {
    Route::view('/menu', 'pages.menu')->name('menu');
});

Route::middleware(['auth', 'permission:kitchen.view'])->group(function () {
    Route::view('/kitchen', 'pages.kitchen')->name('kitchen');
});

Route::middleware(['auth', 'permission:reports.view'])->get('/admin/sales-records/audit-report', function (\Illuminate\Http\Request $request, \App\Domains\PointOfSale\Services\SalesAggregationService $service) {
    $user = auth()->user();

    if (session(\App\Support\Constants\SystemConstants::SESSION_AUTH_METHOD) === 'pin') {
        abort(403, 'Back-office reports cannot be accessed from a floor PIN session.');
    }

    if (! $user->isOwner() && ! $user->can('reports.view')) {
        abort(403);
    }

    $interval = $request->query('interval', 'day');
    $startDate = $request->query('startDate');
    $endDate = $request->query('endDate');
    $requestedBranchId = $request->filled('branchId') ? (int) $request->query('branchId') : null;
    $branchId = $user->effectiveBranchId($requestedBranchId);
    $cashierId = $request->filled('cashierId') ? (int) $request->query('cashierId') : null;

    if ($cashierId && $branchId) {
        $cashierExistsInBranch = \App\Domains\Identity\Models\User::where('id', $cashierId)
            ->where('branch_id', $branchId)
            ->exists();

        if (! $cashierExistsInBranch) {
            $cashierId = null;
        }
    }

    $report = $service->getPeriodicReport($interval, $startDate, $endDate, $branchId, $cashierId);
    $branch = $branchId ? \App\Domains\Branches\Models\Branch::find($branchId) : null;
    $cashier = $cashierId ? \App\Domains\Identity\Models\User::find($cashierId) : null;

    return view('reports.periodic-sales-audit', [
        'report' => $report,
        'interval' => $interval,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'branch' => $branch,
        'cashier' => $cashier,
    ]);
})->name('sales-records.audit-report');

require __DIR__.'/auth.php';
