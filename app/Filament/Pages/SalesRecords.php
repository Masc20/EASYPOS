<?php

namespace App\Filament\Pages;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class SalesRecords extends Page
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && $user->canAccessAdmin()
            && $user->can('reports.view')
            && (! $user->isBranchScoped() || $user->branch_id !== null));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Sales & Transactions';

    protected static ?string $navigationLabel = 'Sales Records';

    protected static ?string $title = 'Sales Records & Financial Summaries';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.sales-records';

    public string $exportStatus = 'paid_or_completed';

    public string $startDate = '';
    public string $endDate = '';
    public string $selectedPreset = 'today';
    public ?int $branchId = null;
    public ?int $cashierId = null;

    // Best-Sellers Leaderboard (SCRUM-167, 168, 169)
    public string $bestSellerSortBy = 'quantity';
    public ?int $bestSellerCategoryId = null;
    public int $bestSellerLimit = 10;

    // Periodic Sales Reports (SCRUM-171, 172, 173)
    public string $periodicInterval = 'day';
    public string $periodicSortColumn = 'period_key';
    public string $periodicSortDirection = 'desc';

    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function mount(): void
    {
        if (auth()->user()?->isBranchScoped()) {
            $this->branchId = auth()->user()->effectiveBranchId();
        }
        $this->setPreset('today');
    }

    public function setPreset(string $preset): void
    {
        $this->selectedPreset = $preset;

        $today = Carbon::today(config('app.business_timezone'));

        switch ($preset) {
            case 'today':
                $this->startDate = $today->toDateString();
                $this->endDate = $today->toDateString();
                break;
            case 'yesterday':
                $this->startDate = $today->copy()->subDay()->toDateString();
                $this->endDate = $today->copy()->subDay()->toDateString();
                break;
            case 'this_week':
                $this->startDate = $today->copy()->startOfWeek()->toDateString();
                $this->endDate = $today->copy()->endOfWeek()->toDateString();
                break;
            case 'this_month':
                $this->startDate = $today->copy()->startOfMonth()->toDateString();
                $this->endDate = $today->copy()->endOfMonth()->toDateString();
                break;
            case 'this_year':
                $this->startDate = $today->copy()->startOfYear()->toDateString();
                $this->endDate = $today->copy()->endOfYear()->toDateString();
                break;
            case 'all_time':
                $this->startDate = '';
                $this->endDate = '';
                break;
        }
    }

    public function setBestSellerSort(string $sortBy): void
    {
        $this->bestSellerSortBy = in_array($sortBy, ['quantity', 'revenue']) ? $sortBy : 'quantity';
    }

    public function setBestSellerCategory(?int $categoryId): void
    {
        $this->bestSellerCategoryId = $categoryId ?: null;
    }

    public function setBestSellerLimit(int $limit): void
    {
        $this->bestSellerLimit = max(5, min(50, $limit));
    }

    public function setPeriodicInterval(string $interval): void
    {
        $normalized = match (strtolower(trim($interval))) {
            'daily', 'day' => 'day',
            'weekly', 'week' => 'week',
            'monthly', 'month' => 'month',
            'yearly', 'year' => 'year',
            default => 'day',
        };
        $this->periodicInterval = $normalized;
    }

    public function sortPeriodic(string $column): void
    {
        if ($this->periodicSortColumn === $column) {
            $this->periodicSortDirection = $this->periodicSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->periodicSortColumn = $column;
            $this->periodicSortDirection = 'desc';
        }
    }

    public function resetFilters(): void
    {
        $this->exportStatus = 'paid_or_completed';
        $this->branchId = auth()->user()?->isBranchScoped()
            ? auth()->user()->effectiveBranchId()
            : null;
        $this->cashierId = null;
        $this->bestSellerCategoryId = null;
        $this->bestSellerSortBy = 'quantity';
        $this->bestSellerLimit = 10;
        $this->periodicInterval = 'day';
        $this->periodicSortColumn = 'period_key';
        $this->periodicSortDirection = 'desc';
        $this->setPreset('today');
    }

    public function exportCsv(SalesAggregationService $service): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['exportStatus' => ['required', \Illuminate\Validation\Rule::in(array_keys(SalesAggregationService::EXPORT_STATUSES))]]);
        $this->skipRender();

        $user = auth()->user();
        $effectiveBranchId = $user ? $user->effectiveBranchId($this->branchId) : $this->branchId;
        $cashierId = $this->cashierId;

        if ($user && $user->isBranchScoped() && $cashierId) {
            $cashierBelongs = User::where('id', $cashierId)->where('branch_id', $effectiveBranchId)->exists();
            if (! $cashierBelongs) {
                $cashierId = null;
            }
        }

        $safeStart = $service->sanitizeFilenameSegment($this->startDate);
        $safeEnd = $service->sanitizeFilenameSegment($this->endDate);
        $filename = 'sales-records-' . $safeStart . '-to-' . $safeEnd . '.csv';

        return response()->streamDownload(function () use ($service, $effectiveBranchId, $cashierId) {
            $handle = fopen('php://output', 'w');
            $service->streamOrdersCsv(
                $handle,
                $this->startDate,
                $this->endDate,
                $effectiveBranchId,
                $cashierId,
                $this->exportStatus
            );
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function exportPeriodicCsv(SalesAggregationService $service): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['exportStatus' => ['required', \Illuminate\Validation\Rule::in(array_keys(SalesAggregationService::EXPORT_STATUSES))]]);
        $this->skipRender();

        $user = auth()->user();
        $effectiveBranchId = $user ? $user->effectiveBranchId($this->branchId) : $this->branchId;
        $cashierId = $this->cashierId;

        if ($cashierId && $effectiveBranchId) {
            $cashierBelongs = User::where('id', $cashierId)->where('branch_id', $effectiveBranchId)->exists();
            if (! $cashierBelongs) {
                $cashierId = null;
            }
        }

        $interval = in_array(strtolower($this->periodicInterval), ['day', 'week', 'month', 'year'])
            ? strtolower($this->periodicInterval)
            : 'day';
        $safeStart = $service->sanitizeFilenameSegment($this->startDate);
        $safeEnd = $service->sanitizeFilenameSegment($this->endDate);
        $filename = 'periodic-sales-' . $interval . '-' . $safeStart . '-to-' . $safeEnd . '.csv';

        return response()->streamDownload(function () use ($service, $interval, $effectiveBranchId, $cashierId) {
            $output = fopen('php://output', 'w');
            $service->streamPeriodicCsv(
                $output,
                $interval,
                $this->startDate,
                $this->endDate,
                $effectiveBranchId,
                $cashierId,
                $this->exportStatus
            );
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $effectiveBranchId = $user ? $user->effectiveBranchId($this->branchId) : $this->branchId;
        $this->branchId = $effectiveBranchId;

        if ($user && $user->isBranchScoped() && $effectiveBranchId < 0) {
            return [
                'summary' => [
                    'gross_sales' => 0.0,
                    'net_sales' => 0.0,
                    'total_discounts' => 0.0,
                    'orders_count' => 0,
                    'average_order_value' => 0.0,
                    'payments_breakdown' => [],
                    'order_types_breakdown' => [],
                    'tax_summary' => [
                        'vatable_sales' => 0.0,
                        'vat_amount' => 0.0,
                        'vat_exempt_sales' => 0.0,
                        'zero_rated_sales' => 0.0,
                    ],
                    'cash_reconciliation' => [
                        'net_cash_in_drawer' => 0.0,
                        'total_cash_tendered' => 0.0,
                        'total_change_given' => 0.0,
                        'total_digital' => 0.0,
                    ],
                    'refunds_summary' => [
                        'refunded_amount' => 0.0,
                        'refunded_count' => 0,
                    ],
                    'top_selling_items' => [],
                    'cashier_performance' => [],
                ],
                'periodicReport' => [
                    'interval' => $this->periodicInterval,
                    'items' => [],
                    'totals' => [
                        'orders_count' => 0,
                        'gross_sales' => 0.0,
                        'total_discounts' => 0.0,
                        'net_sales' => 0.0,
                        'vatable_sales' => 0.0,
                        'vat_amount' => 0.0,
                        'vat_exempt_sales' => 0.0,
                        'average_order_value' => 0.0,
                    ],
                ],
                'branches' => collect(),
                'cashiers' => collect(),
                'isBranchScoped' => true,
            ];
        }

        if ($user && $user->isBranchScoped() && $this->cashierId) {
            $cashierBelongs = User::where('id', $this->cashierId)->where('branch_id', $effectiveBranchId)->exists();
            if (! $cashierBelongs) {
                $this->cashierId = null;
            }
        }

        $service = app(SalesAggregationService::class);
        $summary = $service->getSummary(
            $this->startDate,
            $this->endDate,
            $effectiveBranchId,
            $this->cashierId
        );

        $periodicReport = $service->getPeriodicReport(
            $this->periodicInterval,
            $this->startDate,
            $this->endDate,
            $effectiveBranchId,
            $this->cashierId
        );

        if ($this->periodicSortColumn !== 'period_key' || $this->periodicSortDirection !== 'desc') {
            $col = $this->periodicSortColumn;
            $dir = $this->periodicSortDirection;
            usort($periodicReport['items'], function ($a, $b) use ($col, $dir) {
                $valA = $a[$col] ?? 0;
                $valB = $b[$col] ?? 0;
                if ($valA == $valB) {
                    return 0;
                }
                if ($dir === 'asc') {
                    return $valA < $valB ? -1 : 1;
                }
                return $valA > $valB ? -1 : 1;
            });
        }

        $branches = ($user && $user->isBranchScoped())
            ? Branch::where('id', $effectiveBranchId)->get()
            : Branch::query()->orderBy('name')->get();

        $cashiersQuery = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['cashier', 'branch-manager', 'owner', 'super-admin']))
            ->orderBy('name');

        if ($user && $user->isBranchScoped()) {
            $cashiersQuery->where('branch_id', $effectiveBranchId);
        }

        $cashiers = $cashiersQuery->get();

        return [
            'summary' => $summary,
            'periodicReport' => $periodicReport,
            'branches' => $branches,
            'cashiers' => $cashiers,
            'isBranchScoped' => $user?->isBranchScoped() ?? false,
        ];
    }
}
