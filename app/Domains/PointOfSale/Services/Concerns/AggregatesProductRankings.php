<?php

namespace App\Domains\PointOfSale\Services\Concerns;

use App\Domains\PointOfSale\Models\OrderItem;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait AggregatesProductRankings
{
    /**
     * Build branch-isolated cache key for best-selling rankings.
     */
    public function buildBestSellersCacheKey(
        ?string $startDate,
        ?string $endDate,
        ?int $branchId,
        ?int $cashierId,
        ?int $categoryId,
        int $limit,
        string $sortBy
    ): string {
        $b = $branchId !== null ? (string) $branchId : 'global';
        $c = $cashierId !== null ? (string) $cashierId : 'all';
        $cat = $categoryId !== null ? (string) $categoryId : 'all';
        $s = $startDate ?: 'none';
        $e = $endDate ?: 'none';

        return "sales_best_sellers_b{$b}_c{$c}_{$cat}_{$limit}_{$sortBy}_{$s}_{$e}";
    }

    /**
     * Build branch-isolated cache key for category rankings.
     */
    public function buildCategoryRankingsCacheKey(
        ?string $startDate,
        ?string $endDate,
        ?int $branchId,
        ?int $cashierId
    ): string {
        $b = $branchId !== null ? (string) $branchId : 'global';
        $c = $cashierId !== null ? (string) $cashierId : 'all';
        $s = $startDate ?: 'none';
        $e = $endDate ?: 'none';

        return "sales_cat_rankings_b{$b}_c{$c}_{$s}_{$e}";
    }

    /**
     * Get ranked best-selling menu items with volume, revenue, and category details.
     *
     * @return array<int, array{
     *     rank: int,
     *     meal_id: int,
     *     name: string,
     *     category_id: int,
     *     category_name: string,
     *     quantity: int,
     *     revenue: float,
     *     percentage: float
     * }>
     */
    public function getBestSellingRankings(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null,
        ?int $categoryId = null,
        int $limit = 10,
        string $sortBy = 'quantity',
        bool $useCache = true,
        ?float $totalNetSales = null
    ): array {
        $effectiveBranchId = $this->resolveBranchScope($branchId);

        $calculator = function () use ($startDate, $endDate, $effectiveBranchId, $cashierId, $categoryId, $limit, $sortBy, $totalNetSales) {
            if ($totalNetSales === null) {
                $ordersQuery = $this->buildFilteredOrdersQuery($startDate, $endDate, $effectiveBranchId, $cashierId);
                $totalNetSales = (float) ((clone $ordersQuery)->sum('orders.total_amount') ?: 0.0);
            }

            $query = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->leftJoin('meals', 'order_items.meal_id', '=', 'meals.id')
                ->leftJoin('meal_categories', 'meals.category_id', '=', 'meal_categories.id')
                ->where(function ($q) {
                    $q->where('orders.payment_status', PaymentStatus::PAID->value)
                        ->orWhere('orders.status', OrderStatus::COMPLETED->value);
                });

            if ($startDate) {
                $query->where('orders.created_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
            if ($endDate) {
                $query->where('orders.created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }
            if ($effectiveBranchId) {
                $query->where('orders.branch_id', $effectiveBranchId);
            }
            if ($cashierId) {
                $query->where('orders.cashier_id', $cashierId);
            }
            if ($categoryId) {
                $query->where('meals.category_id', $categoryId);
            }

            $query->select(
                'order_items.meal_id',
                'order_items.meal_name',
                DB::raw("COALESCE(meal_categories.name, 'Uncategorized') as category_name"),
                DB::raw('COALESCE(meal_categories.id, 0) as category_id'),
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy(
                'order_items.meal_id',
                'order_items.meal_name',
                'meal_categories.name',
                'meal_categories.id'
            );

            if ($sortBy === 'revenue') {
                $query->orderByDesc('total_revenue')
                        ->orderByDesc('total_qty')
                        ->orderBy('order_items.meal_name', 'asc');
            } else {
                $query->orderByDesc('total_qty')
                        ->orderByDesc('total_revenue')
                        ->orderBy('order_items.meal_name', 'asc');
            }

            if ($limit > 0) {
                $query->limit($limit);
            }

            $rows = $query->get();

            $rankedItems = [];
            foreach ($rows as $index => $row) {
                $rev = (float) $row->total_revenue;
                $qty = (int) $row->total_qty;
                $rankedItems[] = [
                    'rank' => $index + 1,
                    'meal_id' => (int) $row->meal_id,
                    'name' => $row->meal_name,
                    'category_id' => (int) $row->category_id,
                    'category_name' => $row->category_name,
                    'quantity' => $qty,
                    'revenue' => $rev,
                    'percentage' => $totalNetSales > 0 ? round(($rev / $totalNetSales) * 100, 1) : 0.0,
                ];
            }

            return $rankedItems;
        };

        if ($useCache) {
            $cacheKey = $this->buildBestSellersCacheKey(
                $startDate,
                $endDate,
                $effectiveBranchId,
                $cashierId,
                $categoryId,
                $limit,
                $sortBy
            );

            return Cache::remember($cacheKey, 60, $calculator);
        }

        return $calculator();
    }

    /**
     * Get menu category ranking and sales volume breakdown.
     *
     * @return array<int, array{
     *     rank: int,
     *     category_id: int,
     *     category_name: string,
     *     quantity: int,
     *     revenue: float,
     *     order_count: int,
     *     revenue_percentage: float,
     *     qty_percentage: float
     * }>
     */
    public function getCategoryRankings(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null,
        ?float $totalNetSales = null,
        bool $useCache = true
    ): array {
        $effectiveBranchId = $this->resolveBranchScope($branchId);
        $runtimeKey = "cat_rankings_{$effectiveBranchId}_{$cashierId}_{$startDate}_{$endDate}";

        if (isset($this->runtimeCache[$runtimeKey])) {
            return $this->runtimeCache[$runtimeKey];
        }

        $calculator = function () use ($startDate, $endDate, $effectiveBranchId, $cashierId, $totalNetSales) {
            if ($totalNetSales === null) {
                $ordersQuery = $this->buildFilteredOrdersQuery($startDate, $endDate, $effectiveBranchId, $cashierId);
                $totalNetSales = (float) ((clone $ordersQuery)->sum('orders.total_amount') ?: 0.0);
            }

            $query = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->leftJoin('meals', 'order_items.meal_id', '=', 'meals.id')
                ->leftJoin('meal_categories', 'meals.category_id', '=', 'meal_categories.id')
                ->where(function ($q) {
                    $q->where('orders.payment_status', PaymentStatus::PAID->value)
                        ->orWhere('orders.status', OrderStatus::COMPLETED->value);
                });

            if ($startDate) {
                $query->where('orders.created_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
            if ($endDate) {
                $query->where('orders.created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }
            if ($effectiveBranchId) {
                $query->where('orders.branch_id', $effectiveBranchId);
            }
            if ($cashierId) {
                $query->where('orders.cashier_id', $cashierId);
            }

            $rows = $query->select(
                DB::raw("COALESCE(meal_categories.id, 0) as category_id"),
                DB::raw("COALESCE(meal_categories.name, 'Uncategorized') as category_name"),
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count')
            )
            ->groupBy('meal_categories.id', 'meal_categories.name')
            ->orderByDesc('total_revenue')
            ->orderByDesc('total_qty')
            ->get();

            $totalUnits = $rows->sum('total_qty');

            $categories = [];
            foreach ($rows as $index => $row) {
                $rev = (float) $row->total_revenue;
                $qty = (int) $row->total_qty;
                $categories[] = [
                    'rank' => $index + 1,
                    'category_id' => (int) $row->category_id,
                    'category_name' => $row->category_name,
                    'quantity' => $qty,
                    'revenue' => $rev,
                    'order_count' => (int) $row->order_count,
                    'revenue_percentage' => $totalNetSales > 0 ? round(($rev / $totalNetSales) * 100, 1) : 0.0,
                    'qty_percentage' => $totalUnits > 0 ? round(($qty / $totalUnits) * 100, 1) : 0.0,
                ];
            }

            return $categories;
        };

        if ($useCache) {
            $cacheKey = $this->buildCategoryRankingsCacheKey($startDate, $endDate, $effectiveBranchId, $cashierId);
            $result = Cache::remember($cacheKey, 60, $calculator);
            $this->runtimeCache[$runtimeKey] = $result;
            return $result;
        }

        $result = $calculator();
        $this->runtimeCache[$runtimeKey] = $result;
        return $result;
    }
}
