<?php

namespace App\Domains\PointOfSale\Services;

use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
                COALESCE(SUM(CASE WHEN orders.discount_amount > 0 THEN orders.total_amount ELSE 0 END), 0) as vat_exempt_sales,
                COALESCE(SUM(CASE WHEN orders.discount_amount = 0 THEN (orders.total_amount / 1.12) ELSE 0 END), 0) as vatable_sales,
                COALESCE(SUM(CASE WHEN orders.discount_amount = 0 THEN (orders.total_amount / 1.12) * 0.12 ELSE 0 END), 0) as vat_amount
            ')
            ->first();

        $taxSummary = [
            'vatable_sales' => round((float) ($taxStats->vatable_sales ?? 0.00), 2),
            'vat_amount' => round((float) ($taxStats->vat_amount ?? 0.00), 2),
            'vat_exempt_sales' => round((float) ($taxStats->vat_exempt_sales ?? 0.00), 2),
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
        $topItemsQuery = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where(function ($q) {
                $q->where('orders.payment_status', 'paid')
                    ->orWhere('orders.status', 'completed');
            });

        if ($startDate) {
            $topItemsQuery->where('orders.created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $topItemsQuery->where('orders.created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }
        if ($branchId) {
            $topItemsQuery->where('orders.branch_id', $branchId);
        }
        if ($cashierId) {
            $topItemsQuery->where('orders.cashier_id', $cashierId);
        }

        $topItemsRows = $topItemsQuery
            ->select(
                'order_items.meal_name',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy('order_items.meal_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $topSellingItems = [];
        foreach ($topItemsRows as $item) {
            $rev = (float) $item->total_revenue;
            $topSellingItems[] = [
                'name' => $item->meal_name,
                'quantity' => (int) $item->total_qty,
                'revenue' => $rev,
                'percentage' => $netSales > 0 ? round(($rev / $netSales) * 100, 1) : 0.0,
            ];
        }

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
