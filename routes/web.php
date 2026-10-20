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

Route::middleware(['auth', 'permission:menu.view'])->group(function () {
    Route::view('/menu', 'pages.menu')->name('menu');
});

Route::middleware(['auth', 'permission:kitchen.view'])->group(function () {
    Route::view('/kitchen', 'pages.kitchen')->name('kitchen');
});

Route::middleware(['auth'])->get('/admin/sales-records/audit-report', function (\Illuminate\Http\Request $request, \App\Domains\PointOfSale\Services\SalesAggregationService $service) {
    $interval = $request->query('interval', 'day');
    $startDate = $request->query('startDate');
    $endDate = $request->query('endDate');
    $branchId = $request->filled('branchId') ? (int) $request->query('branchId') : null;
    $cashierId = $request->filled('cashierId') ? (int) $request->query('cashierId') : null;

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
