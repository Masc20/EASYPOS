<?php

namespace App\Domains\PointOfSale\Services\Concerns;

use App\Support\Constants\SystemConstants;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait StreamsSalesCsv
{
    /**
     * Stream filtered orders directly to an output resource (e.g. php://output) in chunks
     * using lightweight stdClass queries (zero Eloquent model hydration, O(1) memory, bounded SQL).
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

        $query = DB::table('orders')
            ->leftJoin('branches', 'orders.branch_id', '=', 'branches.id')
            ->leftJoin('users', 'orders.cashier_id', '=', 'users.id')
            ->whereNull('orders.deleted_at')
            ->where(function ($q) {
                $q->where('orders.payment_status', PaymentStatus::PAID->value)
                    ->orWhere('orders.status', OrderStatus::COMPLETED->value);
            })
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.created_at',
                'orders.order_type',
                'orders.table_number',
                'orders.subtotal',
                'orders.discount_amount',
                'orders.total_amount',
                'orders.status',
                'branches.name as branch_name',
                'users.name as cashier_name',
            ]);

        $parsedStart = $this->parseSafeDate($startDate);
        if ($parsedStart !== null) {
            $query->where('orders.created_at', '>=', $parsedStart->startOfDay());
        }

        $parsedEnd = $this->parseSafeDate($endDate);
        if ($parsedEnd !== null) {
            $query->where('orders.created_at', '<=', $parsedEnd->endOfDay());
        }

        if ($effectiveBranchId !== null) {
            $query->where('orders.branch_id', $effectiveBranchId);
        }

        if ($cashierId) {
            $query->where('orders.cashier_id', $cashierId);
        }

        // Stream orders in 1,000-row chunks with a single batched payment lookup per chunk
        $query->chunkById(1000, function ($orders) use ($output): void {
            $orderIds = $orders->pluck('id')->all();

            $paymentsByOrder = DB::table('payments')
                ->whereIn('order_id', $orderIds)
                ->get(['order_id', 'payment_method'])
                ->groupBy('order_id');

            foreach ($orders as $order) {
                $methods = isset($paymentsByOrder[$order->id])
                    ? $paymentsByOrder[$order->id]->pluck('payment_method')->filter()->unique()->implode(', ')
                    : '';
                $paymentMethod = $methods !== '' ? $methods : 'Unpaid';

                $createdAt = $order->created_at
                    ? Carbon::parse($order->created_at)->format('Y-m-d H:i:s')
                    : '';

                fputcsv($output, [
                    $this->sanitizeCsvCell((string) $order->order_number),
                    $this->sanitizeCsvCell($createdAt),
                    $this->sanitizeCsvCell((string) ($order->branch_name ?? 'Global')),
                    $this->sanitizeCsvCell((string) ($order->cashier_name ?? 'Staff')),
                    $this->sanitizeCsvCell(strtoupper(str_replace('_', ' ', (string) $order->order_type))),
                    $this->sanitizeCsvCell((string) ($order->table_number ?? '—')),
                    number_format((float) $order->subtotal, 2, '.', ''),
                    number_format((float) $order->discount_amount, 2, '.', ''),
                    number_format((float) $order->total_amount, 2, '.', ''),
                    $this->sanitizeCsvCell(ucwords(str_replace('_', ' ', $paymentMethod))),
                    $this->sanitizeCsvCell(ucfirst((string) $order->status)),
                ]);
            }

            fflush($output);
        }, 'orders.id', 'id');

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

    /**
     * Neutralize spreadsheet formula injection (CSV injection) in string cells.
     */
    protected function sanitizeCsvCell(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $trimmed = ltrim($value, " \t\r\n");
        $firstRaw = $value[0];
        $firstTrimmed = $trimmed !== '' ? $trimmed[0] : '';

        if (
            in_array($firstRaw, ['=', '+', '-', '@', "\t", "\r"], true)
            || in_array($firstTrimmed, ['=', '+', '-', '@'], true)
        ) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Sanitize a filename date/filter segment to prevent header injection or path traversal.
     */
    public function sanitizeFilenameSegment(?string $segment, string $fallback = 'all'): string
    {
        $normalized = $this->normalizeDateString($segment);
        if ($normalized !== '') {
            return $normalized;
        }

        $cleaned = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $segment);

        return ($cleaned !== null && $cleaned !== '') ? $cleaned : $fallback;
    }
}
