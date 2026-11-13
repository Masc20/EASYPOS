<?php

namespace App\Domains\PointOfSale\Services\Concerns;

use App\Support\Constants\SystemConstants;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait AggregatesPeriodicTrends
{
    /**
     * Build branch-isolated cache key for hourly sales distribution.
     */
    public function buildHourlyCacheKey(?string $startDate, ?string $endDate, ?int $branchId, ?int $cashierId): string
    {
        $b = $branchId !== null ? (string) $branchId : 'global';
        $c = $cashierId !== null ? (string) $cashierId : 'all';
        $s = $startDate ?: 'none';
        $e = $endDate ?: 'none';

        return "sales_hourly_b{$b}_c{$c}_{$s}_{$e}";
    }

    /**
     * Get trend series for daily net sales chart.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    public function getDailyRevenueTrend(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): array {
        $effectiveBranchId = $this->resolveBranchScope($branchId);

        $orders = $this->buildFilteredOrdersQuery($startDate, $endDate, $effectiveBranchId, $cashierId)
            ->selectRaw('DATE(orders.created_at) as sale_date, SUM(orders.total_amount) as total')
            ->groupBy('sale_date')
            ->orderBy('sale_date', 'asc')
            ->get();

        $labels = [];
        $values = [];

        foreach ($orders as $order) {
            $labels[] = Carbon::parse($order->sale_date)->format('M d');
            $values[] = round((float) $order->total, 2);
        }

        if (empty($labels)) {
            $labels[] = Carbon::parse($startDate ?: now())->format('M d');
            $values[] = 0.00;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Get periodic sales summary grouped by interval (day, week, month, year) with BIR tax breakdown.
     *
     * @return array{
     *     interval: string,
     *     items: array<int, array{
     *         period_key: string,
     *         period_label: string,
     *         orders_count: int,
     *         gross_sales: float,
     *         total_discounts: float,
     *         net_sales: float,
     *         vatable_sales: float,
     *         vat_amount: float,
     *         vat_exempt_sales: float,
     *         average_order_value: float
     *     }>,
     *     totals: array{
     *         orders_count: int,
     *         gross_sales: float,
     *         total_discounts: float,
     *         net_sales: float,
     *         vatable_sales: float,
     *         vat_amount: float,
     *         vat_exempt_sales: float,
     *         average_order_value: float
     *     }
     * }
     */
    public function getPeriodicReport(
        string $interval = 'day',
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): array {
        $effectiveBranchId = $this->resolveBranchScope($branchId);
        $interval = in_array(strtolower($interval), ['day', 'week', 'month', 'year']) ? strtolower($interval) : 'day';
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $periodSql = match ($interval) {
                'week' => "strftime('%Y-W%W', orders.created_at)",
                'month' => "strftime('%Y-%m', orders.created_at)",
                'year' => "strftime('%Y', orders.created_at)",
                default => "strftime('%Y-%m-%d', orders.created_at)",
            };
        } elseif ($driver === 'pgsql') {
            $periodSql = match ($interval) {
                'week' => "TO_CHAR(orders.created_at, 'IYYY-\"W\"IW')",
                'month' => "TO_CHAR(orders.created_at, 'YYYY-MM')",
                'year' => "TO_CHAR(orders.created_at, 'YYYY')",
                default => "TO_CHAR(orders.created_at, 'YYYY-MM-DD')",
            };
        } else {
            // MySQL / MariaDB
            $periodSql = match ($interval) {
                'week' => "DATE_FORMAT(orders.created_at, '%x-W%v')",
                'month' => "DATE_FORMAT(orders.created_at, '%Y-%m')",
                'year' => "DATE_FORMAT(orders.created_at, '%Y')",
                default => "DATE(orders.created_at)",
            };
        }

        $query = $this->buildFilteredOrdersQuery($startDate, $endDate, $effectiveBranchId, $cashierId);

        $vatDivisor = SystemConstants::VAT_DIVISOR;
        $vatRate = SystemConstants::VAT_RATE;

        $results = (clone $query)
            ->selectRaw("
                {$periodSql} as period_key,
                COUNT(orders.id) as orders_count,
                COALESCE(SUM(orders.subtotal), 0) as gross_sales,
                COALESCE(SUM(orders.discount_amount), 0) as total_discounts,
                COALESCE(SUM(orders.total_amount), 0) as net_sales,
                COALESCE(SUM(CASE WHEN (orders.discount_amount + 0) = 0 THEN (orders.total_amount / {$vatDivisor}) ELSE 0 END), 0) as aggregated_vatable_sales,
                COALESCE(SUM(CASE WHEN (orders.discount_amount + 0) = 0 THEN (orders.total_amount / {$vatDivisor}) * {$vatRate} ELSE 0 END), 0) as aggregated_vat_amount,
                COALESCE(SUM(CASE WHEN (orders.discount_amount + 0) > 0 THEN orders.total_amount ELSE 0 END), 0) as aggregated_vat_exempt_sales
            ")
            ->groupBy('period_key')
            ->orderBy('period_key', 'desc')
            ->get();

        $rows = [];
        $totalGross = 0.0;
        $totalNet = 0.0;
        $totalDiscounts = 0.0;
        $totalVatable = 0.0;
        $totalVat = 0.0;
        $totalExempt = 0.0;
        $totalOrders = 0;

        foreach ($results as $item) {
            $key = (string) $item->period_key;
            $orderCount = (int) $item->orders_count;
            $gross = (float) $item->gross_sales;
            $net = (float) $item->net_sales;
            $disc = (float) $item->total_discounts;
            $vatable = round((float) ($item->aggregated_vatable_sales ?? 0.00), 2);
            $vat = round((float) ($item->aggregated_vat_amount ?? 0.00), 2);
            $exempt = round((float) ($item->aggregated_vat_exempt_sales ?? 0.00), 2);
            $aov = $orderCount > 0 ? round($net / $orderCount, 2) : 0.00;

            $totalGross += $gross;
            $totalNet += $net;
            $totalDiscounts += $disc;
            $totalVatable += $vatable;
            $totalVat += $vat;
            $totalExempt += $exempt;
            $totalOrders += $orderCount;

            $label = match ($interval) {
                'week' => str_contains($key, '-W')
                    ? 'Week ' . substr($key, strpos($key, '-W') + 2) . ', ' . substr($key, 0, 4)
                    : 'Week ' . $key,
                'month' => strlen($key) === 7
                    ? Carbon::createFromFormat('Y-m', $key)->format('F Y')
                    : $key,
                'year' => 'Year ' . $key,
                default => Carbon::parse($key)->format('M d, Y'),
            };

            $rows[] = [
                'period_key' => $key,
                'period_label' => $label,
                'orders_count' => $orderCount,
                'gross_sales' => $gross,
                'total_discounts' => $disc,
                'net_sales' => $net,
                'vatable_sales' => $vatable,
                'vat_amount' => $vat,
                'vat_exempt_sales' => $exempt,
                'average_order_value' => $aov,
            ];
        }

        $overallAov = $totalOrders > 0 ? round($totalNet / $totalOrders, 2) : 0.00;

        return [
            'interval' => $interval,
            'items' => $rows,
            'totals' => [
                'orders_count' => $totalOrders,
                'gross_sales' => round($totalGross, 2),
                'total_discounts' => round($totalDiscounts, 2),
                'net_sales' => round($totalNet, 2),
                'vatable_sales' => round($totalVatable, 2),
                'vat_amount' => round($totalVat, 2),
                'vat_exempt_sales' => round($totalExempt, 2),
                'average_order_value' => $overallAov,
            ],
        ];
    }

    /**
     * Get 24-hour distribution of orders and revenue, identifying peak-hour and rush periods.
     *
     * @return array{
     *     hours: array<int, array{hour: int, label: string, orders_count: int, revenue: float, is_peak: bool}>,
     *     peak_hour: ?int,
     *     peak_hour_label: string,
     *     peak_orders_count: int,
     *     peak_revenue: float,
     *     lunch_rush: array{orders_count: int, revenue: float, label: string},
     *     dinner_rush: array{orders_count: int, revenue: float, label: string},
     *     total_orders: int,
     *     total_revenue: float
     * }
     */
    public function getHourlySalesDistribution(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null,
        bool $useCache = true
    ): array {
        $effectiveBranchId = $this->resolveBranchScope($branchId);
        $runtimeKey = "hourly_{$effectiveBranchId}_{$cashierId}_{$startDate}_{$endDate}";

        if (isset($this->runtimeCache[$runtimeKey])) {
            return $this->runtimeCache[$runtimeKey];
        }

        if ($useCache) {
            $cacheKey = $this->buildHourlyCacheKey($startDate, $endDate, $effectiveBranchId, $cashierId);
            $result = Cache::remember($cacheKey, 60, function () use ($startDate, $endDate, $effectiveBranchId, $cashierId) {
                return $this->computeHourlySalesDistribution($startDate, $endDate, $effectiveBranchId, $cashierId);
            });
            $this->runtimeCache[$runtimeKey] = $result;
            return $result;
        }

        $result = $this->computeHourlySalesDistribution($startDate, $endDate, $effectiveBranchId, $cashierId);
        $this->runtimeCache[$runtimeKey] = $result;
        return $result;
    }

    /**
     * Compute 24-hour distribution calculation without cache lookup.
     */
    protected function computeHourlySalesDistribution(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): array {
        $effectiveBranchId = $this->resolveBranchScope($branchId);
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $hourSql = "CAST(strftime('%H', orders.created_at) AS INTEGER)";
        } elseif ($driver === 'pgsql') {
            $hourSql = "EXTRACT(HOUR FROM orders.created_at)::INTEGER";
        } else {
            $hourSql = "HOUR(orders.created_at)";
        }

        $query = $this->buildFilteredOrdersQuery($startDate, $endDate, $effectiveBranchId, $cashierId);

        $results = (clone $query)
            ->selectRaw("
                {$hourSql} as hour_num,
                COUNT(orders.id) as orders_count,
                COALESCE(SUM(orders.total_amount), 0) as net_sales
            ")
            ->groupBy('hour_num')
            ->get()
            ->keyBy('hour_num');

        $hours = [];
        $peakHour = null;
        $maxOrders = 0;
        $maxRevenue = 0.0;
        $totalOrders = 0;
        $totalRevenue = 0.0;

        $lunchRush = ['orders_count' => 0, 'revenue' => 0.0];
        $dinnerRush = ['orders_count' => 0, 'revenue' => 0.0];

        for ($h = 0; $h < 24; $h++) {
            $row = $results->get($h);
            $orderCount = $row ? (int) $row->orders_count : 0;
            $revenue = $row ? (float) $row->net_sales : 0.0;

            $totalOrders += $orderCount;
            $totalRevenue += $revenue;

            // Lunch rush: 11 AM - 2 PM (hours 11, 12, 13)
            if ($h >= 11 && $h <= 13) {
                $lunchRush['orders_count'] += $orderCount;
                $lunchRush['revenue'] += $revenue;
            }

            // Dinner rush: 5 PM - 9 PM (hours 17, 18, 19, 20)
            if ($h >= 17 && $h <= 20) {
                $dinnerRush['orders_count'] += $orderCount;
                $dinnerRush['revenue'] += $revenue;
            }

            if ($orderCount > $maxOrders || ($orderCount === $maxOrders && $orderCount > 0 && $revenue > $maxRevenue)) {
                $maxOrders = $orderCount;
                $maxRevenue = $revenue;
                $peakHour = $h;
            }

            $label = Carbon::createFromTime($h, 0)->format('g A');

            $hours[$h] = [
                'hour' => $h,
                'label' => $label,
                'orders_count' => $orderCount,
                'revenue' => round($revenue, 2),
                'is_peak' => false,
            ];
        }

        if ($peakHour !== null && $maxOrders > 0) {
            $hours[$peakHour]['is_peak'] = true;
            $startLabel = Carbon::createFromTime($peakHour, 0)->format('g:i A');
            $endLabel = Carbon::createFromTime(($peakHour + 1) % 24, 0)->format('g:i A');
            $peakLabel = "{$startLabel} - {$endLabel}";
        } else {
            $peakLabel = 'No Peak Activity';
            $peakHour = null;
        }

        return [
            'hours' => array_values($hours),
            'peak_hour' => $peakHour,
            'peak_hour_label' => $peakLabel,
            'peak_orders_count' => $maxOrders,
            'peak_revenue' => round($maxRevenue, 2),
            'lunch_rush' => [
                'orders_count' => $lunchRush['orders_count'],
                'revenue' => round($lunchRush['revenue'], 2),
                'label' => '11:00 AM - 2:00 PM',
            ],
            'dinner_rush' => [
                'orders_count' => $dinnerRush['orders_count'],
                'revenue' => round($dinnerRush['revenue'], 2),
                'label' => '5:00 PM - 9:00 PM',
            ],
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
        ];
    }
}
