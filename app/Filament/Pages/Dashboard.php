<?php

namespace App\Filament\Pages;

use App\Domains\Branches\Models\Branch;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Smart Sales & Revenue Dashboard';

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.pages.dashboard';

    public string $startDate = '';
    public string $endDate = '';
    public string $selectedPreset = 'today';
    public ?int $branchId = null;

    // Best-sellers controls (SCRUM-167, 168, 169)
    public string $bestSellerSortBy = 'quantity';
    public ?int $bestSellerCategoryId = null;
    public int $bestSellerLimit = 10;

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

    public function resetFilters(): void
    {
        $this->branchId = auth()->user()?->isBranchScoped()
            ? (int) auth()->user()->branch_id
            : null;
        $this->bestSellerCategoryId = null;
        $this->bestSellerSortBy = 'quantity';
        $this->bestSellerLimit = 10;
        $this->setPreset('today');
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $effectiveBranchId = $user ? $user->effectiveBranchId($this->branchId) : $this->branchId;
        $this->branchId = $effectiveBranchId;

        $service = app(SalesAggregationService::class);

        $summary = $service->getSummary(
            $this->startDate,
            $this->endDate,
            $effectiveBranchId,
            null,
            true,
            false // Prune Query 3 (best sellers) and Query 4 (cashier performance)
        );

        $hourlyDistribution = $service->getHourlySalesDistribution(
            $this->startDate,
            $this->endDate,
            $effectiveBranchId
        );

        $netSales = (float) ($summary['net_sales'] ?? 0.0);

        $bestSellingRankings = $service->getBestSellingRankings(
            $this->startDate,
            $this->endDate,
            $effectiveBranchId,
            null,
            $this->bestSellerCategoryId,
            $this->bestSellerLimit,
            $this->bestSellerSortBy,
            true,
            $netSales
        );

        $categoryRankings = $service->getCategoryRankings(
            $this->startDate,
            $this->endDate,
            $effectiveBranchId,
            null,
            $netSales
        );

        $branches = ($user && $user->isBranchScoped())
            ? Branch::where('id', $user->branch_id)->get()
            : Branch::query()->orderBy('name')->get();
        $categories = MealCategory::query()->where('is_active', true)->orderBy('name')->get();

        return [
            'summary' => $summary,
            'hourlyDistribution' => $hourlyDistribution,
            'bestSellingRankings' => $bestSellingRankings,
            'categoryRankings' => $categoryRankings,
            'branches' => $branches,
            'categories' => $categories,
            'isBranchScoped' => $user?->isBranchScoped() ?? false,
        ];
    }
}
