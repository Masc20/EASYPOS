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
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Sales & Transactions';

    protected static ?string $navigationLabel = 'Sales Records';

    protected static ?string $title = 'Sales Records & Financial Summaries';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.sales-records';

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

    public function mount(): void
    {
        if (auth()->user()?->isBranchScoped()) {
            $this->branchId = (int) auth()->user()->branch_id;
        }
        $this->setPreset('today');
    }

    public function setPreset(string $preset): void
    {
        $this->selectedPreset = $preset;

        switch ($preset) {
            case 'today':
                $this->startDate = Carbon::today()->toDateString();
                $this->endDate = Carbon::today()->toDateString();
                break;
            case 'yesterday':
                $this->startDate = Carbon::yesterday()->toDateString();
                $this->endDate = Carbon::yesterday()->toDateString();
                break;
            case 'this_week':
                $this->startDate = Carbon::now()->startOfWeek()->toDateString();
                $this->endDate = Carbon::now()->endOfWeek()->toDateString();
                break;
            case 'this_month':
                $this->startDate = Carbon::now()->startOfMonth()->toDateString();
                $this->endDate = Carbon::now()->endOfMonth()->toDateString();
                break;
            case 'this_year':
                $this->startDate = Carbon::now()->startOfYear()->toDateString();
                $this->endDate = Carbon::now()->endOfYear()->toDateString();
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
        $this->branchId = auth()->user()?->isBranchScoped()
            ? (int) auth()->user()->branch_id
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
        $user = auth()->user();
        $effectiveBranchId = $user ? $user->effectiveBranchId($this->branchId) : $this->branchId;
        $cashierId = $this->cashierId;

        if ($user && $user->isBranchScoped() && $cashierId) {
            $cashierBelongs = User::where('id', $cashierId)->where('branch_id', $effectiveBranchId)->exists();
            if (! $cashierBelongs) {
                $cashierId = null;
            }
        }

        $filename = 'sales-records-' . ($this->startDate ?: 'all') . '-to-' . ($this->endDate ?: 'all') . '.csv';

        return response()->streamDownload(function () use ($service, $effectiveBranchId, $cashierId) {
            $handle = fopen('php://output', 'w');
            $service->streamOrdersCsv(
                $handle,
                $this->startDate,
                $this->endDate,
                $effectiveBranchId,
                $cashierId
            );
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function exportPeriodicCsv(SalesAggregationService $service): StreamedResponse
    {
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
        $filename = 'periodic-sales-' . $interval . '-' . ($this->startDate ?: 'all') . '-to-' . ($this->endDate ?: 'all') . '.csv';

        return response()->streamDownload(function () use ($service, $interval, $effectiveBranchId, $cashierId) {
            $output = fopen('php://output', 'w');
            $service->streamPeriodicCsv(
                $output,
                $interval,
                $this->startDate,
                $this->endDate,
                $effectiveBranchId,
                $cashierId
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
            ? Branch::where('id', $user->branch_id)->get()
            : Branch::query()->orderBy('name')->get();

        $cashiersQuery = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['cashier', 'branch-manager', 'owner', 'super-admin']))
            ->orderBy('name');

        if ($user && $user->isBranchScoped()) {
            $cashiersQuery->where('branch_id', $user->branch_id);
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
