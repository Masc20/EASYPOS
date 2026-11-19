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
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && $user->canAccessAdmin()
            && ($user->isOwner() || $user->can('reports.view'))
            && (! $user->isBranchScoped() || $user->branch_id !== null));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

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
            $this->branchId = auth()->user()->effectiveBranchId(null);
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
        if (! $categoryId) {
            $this->bestSellerCategoryId = null;

            return;
        }

        $user = auth()->user();
        if ($user && $user->isBranchScoped()) {
            $allowed = MealCategory::query()
                ->where('id', $categoryId)
                ->forBranch($user->effectiveBranchId())
                ->exists();

            if (! $allowed) {
                $this->bestSellerCategoryId = null;

                return;
            }
        }

        $this->bestSellerCategoryId = $categoryId;
    }

    public function setBestSellerLimit(int $limit): void
    {
        $this->bestSellerLimit = max(5, min(50, $limit));
    }

    public function resetFilters(): void
    {
        $this->branchId = auth()->user()?->isBranchScoped()
            ? auth()->user()->effectiveBranchId(null)
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
                'hourlyDistribution' => [
                    'hours' => [],
                    'peak_hour' => null,
                    'peak_hour_label' => 'No Peak Activity',
                    'peak_orders_count' => 0,
                    'peak_revenue' => 0.0,
                    'lunch_rush' => ['orders_count' => 0, 'revenue' => 0.0, 'label' => '11:00 AM - 2:00 PM'],
                    'dinner_rush' => ['orders_count' => 0, 'revenue' => 0.0, 'label' => '5:00 PM - 9:00 PM'],
                    'total_orders' => 0,
                    'total_revenue' => 0.0,
                ],
                'bestSellingRankings' => [],
                'categoryRankings' => [],
                'branches' => collect(),
                'categories' => collect(),
                'isBranchScoped' => true,
            ];
        }

        if ($user && $user->isBranchScoped() && $this->bestSellerCategoryId !== null) {
            $categoryAllowed = MealCategory::query()
                ->where('id', $this->bestSellerCategoryId)
                ->forBranch($effectiveBranchId)
                ->exists();

            if (! $categoryAllowed) {
                $this->bestSellerCategoryId = null;
            }
        }

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
            ? Branch::where('id', $effectiveBranchId)->get()
            : Branch::query()->orderBy('name')->get();
        $categories = MealCategory::query()
            ->when($user && $user->isBranchScoped(), fn ($q) => $q->forBranch($effectiveBranchId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

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
