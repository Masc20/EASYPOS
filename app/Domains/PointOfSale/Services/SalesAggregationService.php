<?php

namespace App\Domains\PointOfSale\Services;

use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SalesAggregationService
{
    /**
     * Get aggregated financial and volume summary for a given date range and optional filters.
     *
     * @return array{
     *     gross_sales: float,
     *     net_sales: float,
     *     total_discounts: float,
     *     orders_count: int,
     *     average_order_value: float,
     *     payments_breakdown: array<int, array{method: string, label: string, count: int, total: float, percentage: float}>,
     *     order_types_breakdown: array<string, int>
     * }
     */
    public function getSummary(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): array {
        $ordersQuery = $this->buildFilteredOrdersQuery($startDate, $endDate, $branchId, $cashierId);

        $orderStats = (clone $ordersQuery)
            ->selectRaw('
                COALESCE(SUM(orders.subtotal), 0) as gross_sales,
                COALESCE(SUM(orders.total_amount), 0) as net_sales,
                COALESCE(SUM(orders.discount_amount), 0) as total_discounts,
                COUNT(orders.id) as orders_count
            ')
            ->first();

        $grossSales = (float) ($orderStats->gross_sales ?? 0.00);
        $netSales = (float) ($orderStats->net_sales ?? 0.00);
        $totalDiscounts = (float) ($orderStats->total_discounts ?? 0.00);
        $ordersCount = (int) ($orderStats->orders_count ?? 0);
        $aov = $ordersCount > 0 ? round($netSales / $ordersCount, 2) : 0.00;

        // Payment method breakdown
        $paymentsQuery = $this->buildFilteredPaymentsQuery($startDate, $endDate, $branchId, $cashierId);
        $paymentRows = (clone $paymentsQuery)
            ->select('payment_method', DB::raw('COUNT(id) as txn_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_method')
            ->get();

        $totalPaid = $paymentRows->sum('total_amount');
        $paymentsBreakdown = [];

        foreach ($paymentRows as $row) {
            $amount = (float) $row->total_amount;
            $percentage = $totalPaid > 0 ? round(($amount / $totalPaid) * 100, 1) : 0.0;

            $paymentsBreakdown[] = [
                'method' => $row->payment_method,
                'label' => $this->formatPaymentMethod($row->payment_method),
                'count' => (int) $row->txn_count,
                'total' => $amount,
                'percentage' => $percentage,
            ];
        }

        // Order types breakdown
        $typeRows = (clone $ordersQuery)
            ->select('orders.order_type', DB::raw('COUNT(orders.id) as count'))
            ->groupBy('orders.order_type')
            ->pluck('count', 'orders.order_type')
            ->toArray();

        // BIR & Philippine Tax Summary
        $taxStats = (clone $ordersQuery)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN (orders.discount_amount + 0) > 0 THEN orders.total_amount ELSE 0 END), 0) as aggregated_vat_exempt_sales,
                COALESCE(SUM(CASE WHEN (orders.discount_amount + 0) = 0 THEN (orders.total_amount / 1.12) ELSE 0 END), 0) as aggregated_vatable_sales,
                COALESCE(SUM(CASE WHEN (orders.discount_amount + 0) = 0 THEN (orders.total_amount / 1.12) * 0.12 ELSE 0 END), 0) as aggregated_vat_amount
            ')
            ->first();

        $taxSummary = [
            'vatable_sales' => round((float) ($taxStats->aggregated_vatable_sales ?? 0.00), 2),
            'vat_amount' => round((float) ($taxStats->aggregated_vat_amount ?? 0.00), 2),
            'vat_exempt_sales' => round((float) ($taxStats->aggregated_vat_exempt_sales ?? 0.00), 2),
            'zero_rated_sales' => 0.00,
        ];

        // Cash Register Drawer & Tender Reconciliation
        $cashStats = (clone $paymentsQuery)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN payment_method = \'cash\' THEN amount ELSE 0 END), 0) as net_cash,
                COALESCE(SUM(CASE WHEN payment_method = \'cash\' THEN COALESCE(amount_tendered, amount) ELSE 0 END), 0) as cash_tendered,
                COALESCE(SUM(CASE WHEN payment_method = \'cash\' THEN COALESCE(change_given, 0) ELSE 0 END), 0) as change_given,
                COALESCE(SUM(CASE WHEN payment_method != \'cash\' THEN amount ELSE 0 END), 0) as digital_tendered
            ')
            ->first();

        $cashReconciliation = [
            'net_cash_in_drawer' => (float) ($cashStats->net_cash ?? 0.00),
            'total_cash_tendered' => (float) ($cashStats->cash_tendered ?? 0.00),
            'total_change_given' => (float) ($cashStats->change_given ?? 0.00),
            'total_digital' => (float) ($cashStats->digital_tendered ?? 0.00),
        ];

        // Refunds and Voids Summary
        $refundsQuery = Order::query()
            ->where(function ($q) {
                $q->where('payment_status', 'refunded')
                    ->orWhere('status', 'cancelled');
            });

        if ($startDate) {
            $refundsQuery->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $refundsQuery->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }
        if ($branchId) {
            $refundsQuery->where('branch_id', $branchId);
        }
        if ($cashierId) {
            $refundsQuery->where('cashier_id', $cashierId);
        }

        $refundStats = $refundsQuery->selectRaw('
            COALESCE(SUM(total_amount), 0) as refunded_amount,
            COUNT(id) as refunded_count
        ')->first();

        $refundsSummary = [
            'refunded_amount' => (float) ($refundStats->refunded_amount ?? 0.00),
            'refunded_count' => (int) ($refundStats->refunded_count ?? 0),
        ];

        // Top 5 Best-Selling Menu Items
        $topSellingItems = $this->getBestSellingRankings(
            $startDate,
            $endDate,
            $branchId,
            $cashierId,
            null,
            5,
            'quantity',
            false
        );

        // Cashier & Staff Performance
        $cashierStatsQuery = (clone $ordersQuery)
            ->join('users', 'orders.cashier_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'users.emp_id',
                DB::raw('COUNT(orders.id) as orders_count'),
                DB::raw('SUM(orders.total_amount) as net_revenue'),
                DB::raw('SUM(orders.discount_amount) as discounts_given')
            )
            ->groupBy('users.id', 'users.name', 'users.emp_id')
            ->orderByDesc('net_revenue')
            ->get();

        $cashierPerformance = [];
        foreach ($cashierStatsQuery as $c) {
            $count = (int) $c->orders_count;
            $rev = (float) $c->net_revenue;
            $cashierPerformance[] = [
                'id' => $c->id,
                'name' => $c->name,
                'emp_id' => $c->emp_id ?: 'Staff',
                'orders_count' => $count,
                'net_revenue' => $rev,
                'discounts_given' => (float) $c->discounts_given,
                'avg_ticket' => $count > 0 ? round($rev / $count, 2) : 0.00,
            ];
        }

        return [
            'gross_sales' => $grossSales,
            'net_sales' => $netSales,
            'total_discounts' => $totalDiscounts,
            'orders_count' => $ordersCount,
            'average_order_value' => $aov,
            'payments_breakdown' => $paymentsBreakdown,
            'order_types_breakdown' => $typeRows,
            'tax_summary' => $taxSummary,
            'cash_reconciliation' => $cashReconciliation,
            'refunds_summary' => $refundsSummary,
            'top_selling_items' => $topSellingItems,
            'cashier_performance' => $cashierPerformance,
        ];
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
        $orders = $this->buildFilteredOrdersQuery($startDate, $endDate, $branchId, $cashierId)
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
     * Get distribution series for payment methods chart.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    public function getPaymentMethodDistribution(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): array {
        $payments = $this->buildFilteredPaymentsQuery($startDate, $endDate, $branchId, $cashierId)
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get();

        $labels = [];
        $values = [];

        foreach ($payments as $payment) {
            $labels[] = $this->formatPaymentMethod($payment->payment_method);
            $values[] = round((float) $payment->total, 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Export sales records as a formatted CSV string.
     */
    public function exportCsv(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): string {
        $orders = $this->buildFilteredOrdersQuery($startDate, $endDate, $branchId, $cashierId)
            ->with(['branch', 'cashier', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $handle = fopen('php://temp', 'r+');

        // CSV Headers
        fputcsv($handle, [
            'Order #',
            'Date & Time',
            'Branch',
            'Cashier',
            'Dining Option',
            'Table',
            'Gross Subtotal (PHP)',
            'Discount (PHP)',
            'Net Total (PHP)',
            'Payment Method',
            'Status',
        ]);

        foreach ($orders as $order) {
            $paymentMethod = $order->payments->pluck('payment_method')->unique()->implode(', ') ?: 'Unpaid';

            fputcsv($handle, [
                $order->order_number,
                $order->created_at?->format('Y-m-d H:i:s') ?? '',
                $order->branch?->name ?? 'Global',
                $order->cashier?->name ?? 'Staff',
                strtoupper(str_replace('_', ' ', $order->order_type)),
                $order->table_number ?? '—',
                number_format((float) $order->subtotal, 2, '.', ''),
                number_format((float) $order->discount_amount, 2, '.', ''),
                number_format((float) $order->total_amount, 2, '.', ''),
                ucwords(str_replace('_', ' ', $paymentMethod)),
                ucfirst($order->status),
            ]);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return $csvContent ?: '';
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
        bool $useCache = true
    ): array {
        $cacheKey = 'sales_best_sellers_' . md5(json_encode([
            $startDate,
            $endDate,
            $branchId,
            $cashierId,
            $categoryId,
            $limit,
            $sortBy,
        ]));

        $calculator = function () use ($startDate, $endDate, $branchId, $cashierId, $categoryId, $limit, $sortBy) {
            $ordersQuery = $this->buildFilteredOrdersQuery($startDate, $endDate, $branchId, $cashierId);
            $totalNetSales = (float) ((clone $ordersQuery)->sum('orders.total_amount') ?: 0.0);

            $query = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->leftJoin('meals', 'order_items.meal_id', '=', 'meals.id')
                ->leftJoin('meal_categories', 'meals.category_id', '=', 'meal_categories.id')
                ->where(function ($q) {
                    $q->where('orders.payment_status', 'paid')
                        ->orWhere('orders.status', 'completed');
                });

            if ($startDate) {
                $query->where('orders.created_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
            if ($endDate) {
                $query->where('orders.created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }
            if ($branchId) {
                $query->where('orders.branch_id', $branchId);
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
        ?int $cashierId = null
    ): array {
        $ordersQuery = $this->buildFilteredOrdersQuery($startDate, $endDate, $branchId, $cashierId);
        $totalNetSales = (float) ((clone $ordersQuery)->sum('orders.total_amount') ?: 0.0);

        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('meals', 'order_items.meal_id', '=', 'meals.id')
            ->leftJoin('meal_categories', 'meals.category_id', '=', 'meal_categories.id')
            ->where(function ($q) {
                $q->where('orders.payment_status', 'paid')
                    ->orWhere('orders.status', 'completed');
            });

        if ($startDate) {
            $query->where('orders.created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $query->where('orders.created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }
        if ($branchId) {
            $query->where('orders.branch_id', $branchId);
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

        $query = $this->buildFilteredOrdersQuery($startDate, $endDate, $branchId, $cashierId);

        $results = (clone $query)
            ->selectRaw("
                {$periodSql} as period_key,
                COUNT(orders.id) as orders_count,
                COALESCE(SUM(orders.subtotal), 0) as gross_sales,
                COALESCE(SUM(orders.discount_amount), 0) as total_discounts,
                COALESCE(SUM(orders.total_amount), 0) as net_sales,
                COALESCE(SUM(CASE WHEN (orders.discount_amount + 0) = 0 THEN (orders.total_amount / 1.12) ELSE 0 END), 0) as aggregated_vatable_sales,
                COALESCE(SUM(CASE WHEN (orders.discount_amount + 0) = 0 THEN (orders.total_amount / 1.12) * 0.12 ELSE 0 END), 0) as aggregated_vat_amount,
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
     * Export periodic sales report as CSV.
     */
    public function exportPeriodicCsv(
        string $interval = 'day',
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): string {
        $report = $this->getPeriodicReport($interval, $startDate, $endDate, $branchId, $cashierId);

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Period (' . ucfirst($interval) . ')',
            'Orders Count',
            'Gross Sales (PHP)',
            'Discounts Granted (PHP)',
            'Net Sales (PHP)',
            'VATable Sales (PHP)',
            'VAT Amount 12% (PHP)',
            'VAT-Exempt Sales (PHP)',
            'Average Ticket (PHP)',
        ]);

        foreach ($report['items'] as $row) {
            fputcsv($handle, [
                $row['period_label'],
                $row['orders_count'],
                number_format($row['gross_sales'], 2, '.', ''),
                number_format($row['total_discounts'], 2, '.', ''),
                number_format($row['net_sales'], 2, '.', ''),
                number_format($row['vatable_sales'], 2, '.', ''),
                number_format($row['vat_amount'], 2, '.', ''),
                number_format($row['vat_exempt_sales'], 2, '.', ''),
                number_format($row['average_order_value'], 2, '.', ''),
            ]);
        }

        // Summary Totals row
        $totals = $report['totals'];
        fputcsv($handle, [
            'TOTAL / SUMMARY',
            $totals['orders_count'],
            number_format($totals['gross_sales'], 2, '.', ''),
            number_format($totals['total_discounts'], 2, '.', ''),
            number_format($totals['net_sales'], 2, '.', ''),
            number_format($totals['vatable_sales'], 2, '.', ''),
            number_format($totals['vat_amount'], 2, '.', ''),
            number_format($totals['vat_exempt_sales'], 2, '.', ''),
            number_format($totals['average_order_value'], 2, '.', ''),
        ]);

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return $csvContent ?: '';
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
        ?int $cashierId = null
    ): array {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $hourSql = "CAST(strftime('%H', orders.created_at) AS INTEGER)";
        } elseif ($driver === 'pgsql') {
            $hourSql = "EXTRACT(HOUR FROM orders.created_at)::INTEGER";
        } else {
            $hourSql = "HOUR(orders.created_at)";
        }

        $query = $this->buildFilteredOrdersQuery($startDate, $endDate, $branchId, $cashierId);

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

    /**
     * Build base query for filtered orders.
     */
    protected function buildFilteredOrdersQuery(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): Builder {
        $query = Order::query()
            ->where(function ($q) {
                $q->where('orders.payment_status', 'paid')
                    ->orWhere('orders.status', 'completed');
            });

        if ($startDate) {
            $query->where('orders.created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('orders.created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($branchId) {
            $query->where('orders.branch_id', $branchId);
        }

        if ($cashierId) {
            $query->where('orders.cashier_id', $cashierId);
        }

        return $query;
    }

    /**
     * Build base query for filtered payments.
     */
    protected function buildFilteredPaymentsQuery(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): Builder {
        $query = Payment::query()->where('status', 'successful');

        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($cashierId) {
            $query->where('cashier_id', $cashierId);
        }

        return $query;
    }

    /**
     * Helper to humanize payment method keys.
     */
    protected function formatPaymentMethod(string $method): string
    {
        return match ($method) {
            'cash' => 'Cash',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'qr_transfer' => 'QR Ph / GCash / Maya',
            'voucher' => 'Gift Voucher / Coupon',
            default => ucwords(str_replace('_', ' ', $method)),
        };
    }
}
