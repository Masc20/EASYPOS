<?php

namespace App\Domains\PointOfSale\Services\Concerns;

use App\Domains\PointOfSale\Models\Order;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait AggregatesSalesSummary
{
    /**
     * Build branch-isolated cache key for sales summary.
     */
    public function buildSummaryCacheKey(
        ?string $startDate,
        ?string $endDate,
        ?int $branchId,
        ?int $cashierId,
        bool $includeDetails = true
    ): string {
        $b = $branchId !== null ? (string) $branchId : 'global';
        $c = $cashierId !== null ? (string) $cashierId : 'all';
        $s = $startDate ?: 'none';
        $e = $endDate ?: 'none';
        $d = $includeDetails ? '' : '_min';

        return "sales_summary_b{$b}_c{$c}_{$s}_{$e}{$d}";
    }

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
        ?int $cashierId = null,
        bool $useCache = true,
        bool $includeDetails = true
    ): array {
        $effectiveBranchId = $this->resolveBranchScope($branchId);
        $runtimeKey = "summary_{$effectiveBranchId}_{$cashierId}_{$startDate}_{$endDate}_" . ($includeDetails ? '1' : '0');
        $fullRuntimeKey = "summary_{$effectiveBranchId}_{$cashierId}_{$startDate}_{$endDate}_1";

        if (isset($this->runtimeCache[$runtimeKey])) {
            return $this->runtimeCache[$runtimeKey];
        }

        if (! $includeDetails && isset($this->runtimeCache[$fullRuntimeKey])) {
            return $this->runtimeCache[$fullRuntimeKey];
        }

        if ($useCache) {
            if (! $includeDetails) {
                $fullCacheKey = $this->buildSummaryCacheKey($startDate, $endDate, $effectiveBranchId, $cashierId, true);
                $cachedFull = Cache::get($fullCacheKey);
                if ($cachedFull !== null) {
                    $this->runtimeCache[$runtimeKey] = $cachedFull;
                    return $cachedFull;
                }
            }

            $cacheKey = $this->buildSummaryCacheKey($startDate, $endDate, $effectiveBranchId, $cashierId, $includeDetails);
            $result = Cache::remember($cacheKey, 60, function () use ($startDate, $endDate, $effectiveBranchId, $cashierId, $includeDetails) {
                return $this->computeSummary($startDate, $endDate, $effectiveBranchId, $cashierId, $includeDetails);
            });
            $this->runtimeCache[$runtimeKey] = $result;
            return $result;
        }

        $result = $this->computeSummary($startDate, $endDate, $effectiveBranchId, $cashierId, $includeDetails);
        $this->runtimeCache[$runtimeKey] = $result;
        return $result;
    }

    /**
     * Compute summary calculations without cache lookup.
     */
    protected function computeSummary(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null,
        bool $includeDetails = true
    ): array {
        $vatDivisor = SystemConstants::VAT_DIVISOR;
        $vatRate = SystemConstants::VAT_RATE;

        // Base query with date, branch, and cashier filtering
        $baseOrdersQuery = Order::query();
        if ($startDate) {
            $baseOrdersQuery->where('orders.created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $baseOrdersQuery->where('orders.created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }
        if ($branchId) {
            $baseOrdersQuery->where('orders.branch_id', $branchId);
        }
        if ($cashierId) {
            $baseOrdersQuery->where('orders.cashier_id', $cashierId);
        }

        // Consolidated Query 1: Financials, tax compliance, order types, and refunds/voids on orders
        $orderStats = (clone $baseOrdersQuery)
            ->where(function ($q) {
                $q->where('orders.payment_status', PaymentStatus::PAID->value)
                    ->orWhere('orders.status', OrderStatus::COMPLETED->value)
                    ->orWhere('orders.payment_status', PaymentStatus::REFUNDED->value)
                    ->orWhere('orders.status', OrderStatus::CANCELLED->value);
            })
            ->selectRaw("
                COALESCE(SUM(CASE WHEN (orders.payment_status = 'paid' OR orders.status = 'completed') AND orders.payment_status != 'refunded' AND orders.status != 'cancelled' THEN orders.subtotal ELSE 0 END), 0) as gross_sales,
                COALESCE(SUM(CASE WHEN (orders.payment_status = 'paid' OR orders.status = 'completed') AND orders.payment_status != 'refunded' AND orders.status != 'cancelled' THEN orders.total_amount ELSE 0 END), 0) as net_sales,
                COALESCE(SUM(CASE WHEN (orders.payment_status = 'paid' OR orders.status = 'completed') AND orders.payment_status != 'refunded' AND orders.status != 'cancelled' THEN orders.discount_amount ELSE 0 END), 0) as total_discounts,
                COUNT(CASE WHEN (orders.payment_status = 'paid' OR orders.status = 'completed') AND orders.payment_status != 'refunded' AND orders.status != 'cancelled' THEN orders.id ELSE NULL END) as orders_count,
                COALESCE(SUM(CASE WHEN (orders.payment_status = 'paid' OR orders.status = 'completed') AND orders.payment_status != 'refunded' AND orders.status != 'cancelled' AND (orders.discount_amount + 0) > 0 THEN orders.total_amount ELSE 0 END), 0) as aggregated_vat_exempt_sales,
                COALESCE(SUM(CASE WHEN (orders.payment_status = 'paid' OR orders.status = 'completed') AND orders.payment_status != 'refunded' AND orders.status != 'cancelled' AND (orders.discount_amount + 0) = 0 THEN (orders.total_amount / {$vatDivisor}) ELSE 0 END), 0) as aggregated_vatable_sales,
                COALESCE(SUM(CASE WHEN (orders.payment_status = 'paid' OR orders.status = 'completed') AND orders.payment_status != 'refunded' AND orders.status != 'cancelled' AND (orders.discount_amount + 0) = 0 THEN (orders.total_amount / {$vatDivisor}) * {$vatRate} ELSE 0 END), 0) as aggregated_vat_amount,
                COALESCE(SUM(CASE WHEN (orders.payment_status = 'paid' OR orders.status = 'completed') AND orders.payment_status != 'refunded' AND orders.status != 'cancelled' AND orders.order_type = 'dine_in' THEN 1 ELSE 0 END), 0) as dine_in_count,
                COALESCE(SUM(CASE WHEN (orders.payment_status = 'paid' OR orders.status = 'completed') AND orders.payment_status != 'refunded' AND orders.status != 'cancelled' AND orders.order_type = 'takeout' THEN 1 ELSE 0 END), 0) as takeout_count,
                COALESCE(SUM(CASE WHEN orders.payment_status = 'refunded' OR orders.status = 'cancelled' THEN orders.total_amount ELSE 0 END), 0) as refunded_amount,
                COUNT(CASE WHEN orders.payment_status = 'refunded' OR orders.status = 'cancelled' THEN orders.id ELSE NULL END) as refunded_count
            ")
            ->first();

        $grossSales = (float) ($orderStats->gross_sales ?? 0.00);
        $netSales = (float) ($orderStats->net_sales ?? 0.00);
        $totalDiscounts = (float) ($orderStats->total_discounts ?? 0.00);
        $ordersCount = (int) ($orderStats->orders_count ?? 0);
        $aov = $ordersCount > 0 ? round($netSales / $ordersCount, 2) : 0.00;

        $typeRows = [];
        $dineIn = (int) ($orderStats->dine_in_count ?? 0);
        $takeout = (int) ($orderStats->takeout_count ?? 0);
        if ($dineIn > 0) {
            $typeRows['dine_in'] = $dineIn;
        }
        if ($takeout > 0) {
            $typeRows['takeout'] = $takeout;
        }

        $taxSummary = [
            'vatable_sales' => round((float) ($orderStats->aggregated_vatable_sales ?? 0.00), 2),
            'vat_amount' => round((float) ($orderStats->aggregated_vat_amount ?? 0.00), 2),
            'vat_exempt_sales' => round((float) ($orderStats->aggregated_vat_exempt_sales ?? 0.00), 2),
            'zero_rated_sales' => 0.00,
        ];

        $refundsSummary = [
            'refunded_amount' => (float) ($orderStats->refunded_amount ?? 0.00),
            'refunded_count' => (int) ($orderStats->refunded_count ?? 0),
        ];

        // Consolidated Query 2: Payment method breakdown and cash drawer reconciliation on payments
        $paymentsQuery = $this->buildFilteredPaymentsQuery($startDate, $endDate, $branchId, $cashierId);
        $paymentRows = (clone $paymentsQuery)
            ->selectRaw("
                payment_method,
                COUNT(id) as txn_count,
                SUM(amount) as total_amount,
                COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END), 0) as net_cash,
                COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN COALESCE(amount_tendered, amount) ELSE 0 END), 0) as cash_tendered,
                COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN COALESCE(change_given, 0) ELSE 0 END), 0) as change_given,
                COALESCE(SUM(CASE WHEN payment_method != 'cash' THEN amount ELSE 0 END), 0) as digital_tendered
            ")
            ->groupBy('payment_method')
            ->get();

        $totalPaid = (float) $paymentRows->sum('total_amount');
        $paymentsBreakdown = [];
        $netCash = 0.0;
        $cashTendered = 0.0;
        $changeGiven = 0.0;
        $digitalTendered = 0.0;

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

            $netCash += (float) $row->net_cash;
            $cashTendered += (float) $row->cash_tendered;
            $changeGiven += (float) $row->change_given;
            $digitalTendered += (float) $row->digital_tendered;
        }

        $cashReconciliation = [
            'net_cash_in_drawer' => $netCash,
            'total_cash_tendered' => $cashTendered,
            'total_change_given' => $changeGiven,
            'total_digital' => $digitalTendered,
        ];

        // If no orders exist or details are not requested, avoid executing downstream queries
        if ($ordersCount === 0 || ! $includeDetails) {
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
                'top_selling_items' => [],
                'cashier_performance' => [],
            ];
        }

        // Query 3: Top 5 Best-Selling Menu Items
        $topSellingItems = $this->getBestSellingRankings(
            $startDate,
            $endDate,
            $branchId,
            $cashierId,
            null,
            5,
            'quantity',
            false,
            $netSales
        );

        // Query 4: Cashier & Staff Performance
        $ordersQuery = $this->buildFilteredOrdersQuery($startDate, $endDate, $branchId, $cashierId);
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
     * Get distribution series for payment methods chart (deduplicated by delegating to cached getSummary).
     *
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    public function getPaymentMethodDistribution(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): array {
        $effectiveBranchId = $this->resolveBranchScope($branchId);
        $summary = $this->getSummary($startDate, $endDate, $effectiveBranchId, $cashierId, true, false);

        $labels = [];
        $values = [];

        foreach ($summary['payments_breakdown'] as $payment) {
            $labels[] = $payment['label'];
            $values[] = round((float) $payment['total'], 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }
}
