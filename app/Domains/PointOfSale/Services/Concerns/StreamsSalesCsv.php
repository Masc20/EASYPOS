<?php

namespace App\Domains\PointOfSale\Services\Concerns;

use App\Support\Constants\SystemConstants;

trait StreamsSalesCsv
{
    /**
     * Stream filtered orders directly to an output resource (e.g. php://output) in chunks.
     *
     * @param resource $output
     */
    public function streamOrdersCsv(
        $output,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): void {
        $effectiveBranchId = $this->resolveBranchScope($branchId);

        // Emit UTF-8 BOM for Microsoft Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Output CSV Headers
        fputcsv($output, [
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

        $query = $this->buildFilteredOrdersQuery($startDate, $endDate, $effectiveBranchId, $cashierId)
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.created_at',
                'orders.branch_id',
                'orders.cashier_id',
                'orders.order_type',
                'orders.table_number',
                'orders.subtotal',
                'orders.discount_amount',
                'orders.total_amount',
                'orders.status',
            ])
            ->with([
                'branch:id,name',
                'cashier:id,name',
                'payments:id,order_id,payment_method',
            ]);

        // Stream orders in bounded chunks using lazyById(500) for O(1) memory
        foreach ($query->lazyById(500) as $order) {
            $paymentMethod = $order->payments->pluck('payment_method')->unique()->implode(', ') ?: 'Unpaid';

            fputcsv($output, [
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

        fflush($output);

        if (!app()->runningInConsole() && !app()->environment('testing')) {
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            @flush();
        }
    }

    /**
     * Export sales records as a formatted CSV string (buffered wrapper over streamOrdersCsv).
     */
    public function exportCsv(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): string {
        $handle = fopen('php://temp', 'r+');
        $this->streamOrdersCsv($handle, $startDate, $endDate, $branchId, $cashierId);
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return $csvContent ?: '';
    }

    /**
     * Stream periodic sales report directly to an output resource handle.
     *
     * @param resource $output
     */
    public function streamPeriodicCsv(
        $output,
        string $interval = 'day',
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): void {
        $effectiveBranchId = $this->resolveBranchScope($branchId);
        $report = $this->getPeriodicReport($interval, $startDate, $endDate, $effectiveBranchId, $cashierId);

        // Emit UTF-8 BOM for Microsoft Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        $currency = SystemConstants::CURRENCY_CODE;
        $vatPct = SystemConstants::VAT_PERCENTAGE;
        $normalizedInterval = $report['interval'] ?? (in_array(strtolower($interval), ['day', 'week', 'month', 'year']) ? strtolower($interval) : 'day');

        fputcsv($output, [
            'Period (' . ucfirst($normalizedInterval) . ')',
            'Orders Count',
            "Gross Sales ({$currency})",
            "Discounts Granted ({$currency})",
            "Net Sales ({$currency})",
            "VATable Sales ({$currency})",
            "VAT Amount {$vatPct}% ({$currency})",
            "VAT-Exempt Sales ({$currency})",
            "Average Ticket ({$currency})",
        ]);

        foreach ($report['items'] as $row) {
            fputcsv($output, [
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
        fputcsv($output, [
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

        fflush($output);

        if (!app()->runningInConsole() && !app()->environment('testing')) {
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            @flush();
        }
    }

    /**
     * Export periodic sales report as CSV (buffered wrapper over streamPeriodicCsv).
     */
    public function exportPeriodicCsv(
        string $interval = 'day',
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): string {
        $handle = fopen('php://temp', 'r+');
        $this->streamPeriodicCsv($handle, $interval, $startDate, $endDate, $branchId, $cashierId);
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return $csvContent ?: '';
    }
}
