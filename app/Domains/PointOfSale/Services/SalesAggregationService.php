<?php

namespace App\Domains\PointOfSale\Services;

use App\Domains\PointOfSale\Models\Order;
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
                COALESCE(SUM(subtotal), 0) as gross_sales,
                COALESCE(SUM(total_amount), 0) as net_sales,
                COALESCE(SUM(discount_amount), 0) as total_discounts,
                COUNT(id) as orders_count
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
            ->select('order_type', DB::raw('COUNT(id) as count'))
            ->groupBy('order_type')
            ->pluck('count', 'order_type')
            ->toArray();

        return [
            'gross_sales' => $grossSales,
            'net_sales' => $netSales,
            'total_discounts' => $totalDiscounts,
            'orders_count' => $ordersCount,
            'average_order_value' => $aov,
            'payments_breakdown' => $paymentsBreakdown,
            'order_types_breakdown' => $typeRows,
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
                $q->where('payment_status', 'paid')
                    ->orWhere('status', 'completed');
            });

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
