<?php

use App\Http\Controllers\TerminalLockController;
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

Route::middleware(['auth', 'permission:pos.access', 'throttle:pos'])->group(function () {
    Route::view('/pos', 'pages.pos')->name('pos');
});

Route::middleware(['auth', 'permission:menu.view', 'throttle:menu'])->group(function () {
    Route::view('/menu', 'pages.menu')->name('menu');
});

Route::middleware(['auth', 'permission:kitchen.view', 'throttle:pos'])->group(function () {
    Route::view('/kitchen', 'pages.kitchen')->name('kitchen');
});

Route::middleware(['auth', 'permission:reports.view', 'throttle:reports'])->get('/admin/sales-records/audit-report', function (\Illuminate\Http\Request $request, \App\Domains\PointOfSale\Services\SalesAggregationService $service) {
    $user = auth()->user();

    if (session(\App\Support\Constants\SystemConstants::SESSION_AUTH_METHOD) === 'pin') {
        abort(403, 'Back-office reports cannot be accessed from a floor PIN session.');
    }

    if (! $user->canAccessAdmin() || ! $user->can('reports.view')) {
        abort(403);
    }

    $interval = $request->query('interval', 'day');
    $startDate = $service->normalizeDateString($request->query('startDate')) ?: null;
    $endDate = $service->normalizeDateString($request->query('endDate')) ?: null;
    $requestedBranchId = $request->filled('branchId') ? (int) $request->query('branchId') : null;
    $branchId = $user->effectiveBranchId($requestedBranchId);
    $cashierId = $request->filled('cashierId') ? (int) $request->query('cashierId') : null;

    if ($cashierId && $branchId !== null) {
        $cashierExistsInBranch = \App\Domains\Identity\Models\User::where('id', $cashierId)
            ->where('branch_id', $branchId)
            ->exists();

        if (! $cashierExistsInBranch) {
            $cashierId = null;
        }
    }

    $report = $service->getPeriodicReport($interval, $startDate, $endDate, $branchId, $cashierId);
    $branch = ($branchId !== null && $branchId > 0) ? \App\Domains\Branches\Models\Branch::find($branchId) : null;
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

$exportSalesRecords = function (\Illuminate\Http\Request $request, \App\Domains\PointOfSale\Services\SalesAggregationService $service) {
    $user = auth()->user();

    if (session(\App\Support\Constants\SystemConstants::SESSION_AUTH_METHOD) === 'pin') {
        abort(403, 'Back-office exports cannot be accessed from a floor PIN session.');
    }

    if (! $user->canAccessAdmin() || ! $user->can('reports.view')) {
        abort(403);
    }

    $validated = $request->validate([
        'status' => ['sometimes', 'string', \Illuminate\Validation\Rule::in(array_keys($service::EXPORT_STATUSES))],
        'interval' => ['sometimes', 'string', \Illuminate\Validation\Rule::in(['day', 'week', 'month', 'year'])],
    ]);
    $status = $validated['status'] ?? 'paid_or_completed';
    $interval = $validated['interval'] ?? 'day';
    $periodic = str_contains($request->route()->getName(), 'periodic');

    $startDate = $request->filled('startDate') ? $service->normalizeDateString((string) $request->query('startDate')) : '';
    $endDate = $request->filled('endDate') ? $service->normalizeDateString((string) $request->query('endDate')) : '';
    $requestedBranchId = $request->filled('branchId') ? (int) $request->query('branchId') : null;
    $effectiveBranchId = $user->effectiveBranchId($requestedBranchId);
    $cashierId = $request->filled('cashierId') ? (int) $request->query('cashierId') : null;

    if ($user->isBranchScoped() && $cashierId) {
        $cashierBelongs = \App\Domains\Identity\Models\User::where('id', $cashierId)
            ->where('branch_id', $effectiveBranchId)
            ->exists();
        if (! $cashierBelongs) {
            $cashierId = null;
        }
    }

    $safeStart = $service->sanitizeFilenameSegment($startDate);
    $safeEnd = $service->sanitizeFilenameSegment($endDate);
    $format = str_ends_with($request->route()->getName(), 'excel') ? 'xlsx' : 'csv';
    $prefix = $periodic ? 'periodic-sales-' . $interval : 'sales-records';
    $statusSuffix = $status === 'paid_or_completed' ? '' : '-' . $status;
    $filename = $prefix . '-' . $safeStart . '-to-' . $safeEnd . $statusSuffix . '.' . $format;

    return response()->streamDownload(function () use ($service, $startDate, $endDate, $effectiveBranchId, $cashierId, $status, $interval, $periodic, $format) {
        if ($format === 'xlsx') {
            if ($periodic) {
                $service->streamPeriodicExcel($interval, $startDate, $endDate, $effectiveBranchId, $cashierId, $status);
            } else {
                $service->streamOrdersExcel($startDate, $endDate, $effectiveBranchId, $cashierId, $status);
            }
            return;
        }
        $handle = fopen('php://output', 'w');
        try {
            if ($periodic) {
                $service->streamPeriodicCsv($handle, $interval, $startDate, $endDate, $effectiveBranchId, $cashierId, $status);
            } else {
                $service->streamOrdersCsv($handle, $startDate, $endDate, $effectiveBranchId, $cashierId, $status);
            }
        } finally {
            fclose($handle);
        }
    }, $filename, [
        'Content-Type' => $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8',
        'X-Accel-Buffering' => 'no',
    ]);
};

Route::middleware(['auth', 'permission:reports.view', 'throttle:reports'])->group(function () use ($exportSalesRecords) {
    Route::get('/admin/sales-records/export-csv', $exportSalesRecords)->name('sales-records.export-csv');
    Route::get('/admin/sales-records/export-excel', $exportSalesRecords)->name('sales-records.export-excel');
    Route::get('/admin/sales-records/export-periodic-csv', $exportSalesRecords)->name('sales-records.export-periodic-csv');
    Route::get('/admin/sales-records/export-periodic-excel', $exportSalesRecords)->name('sales-records.export-periodic-excel');
});

Route::middleware('auth')->group(function () {
    Route::get('/terminal/lock', [TerminalLockController::class, 'show'])->name('terminal.lock.show');
    Route::post('/terminal/lock', [TerminalLockController::class, 'lock'])->name('terminal.lock');
    Route::post('/terminal/unlock', [TerminalLockController::class, 'unlock'])
        ->middleware('throttle:30,1')
        ->name('terminal.unlock');
});

require __DIR__.'/auth.php';
