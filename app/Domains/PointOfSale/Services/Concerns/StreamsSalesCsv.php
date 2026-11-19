<?php

namespace App\Domains\PointOfSale\Services\Concerns;

use App\Support\Constants\SystemConstants;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

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
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $this->orderExportHeaders());
        $this->writeOrderExportRows(function (array $row) use ($output): void {
            foreach ([0, 1, 2, 3, 4, 5, 9, 10] as $index) {
                $row[$index] = $this->sanitizeCsvCell($row[$index]);
            }
            fputcsv($output, $row);
            fflush($output);
        }, $startDate, $endDate, $branchId, $cashierId);
        fflush($output);
    }

    public function streamOrdersExcel(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): void {
        $options = new Options();
        $options->DEFAULT_COLUMN_WIDTH = 22;
        $options->setColumnWidth(28, 2, 3, 4);
        $writer = new Writer($options);
        $writer->openToFile('php://output');

        try {
            $writer->getCurrentSheet()->setName('Sales Records');
            $headerStyle = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('166534');
            $writer->addRow(Row::fromValues($this->orderExportHeaders(), $headerStyle));
            $moneyStyle = (new Style())->setFormat('#,##0.00');
            $dateStyle = (new Style())->setFormat('yyyy-mm-dd hh:mm:ss');
            $this->writeOrderExportRows(function (array $values) use ($writer, $moneyStyle, $dateStyle): void {
                // Explicit text cells preserve identifiers and prevent formula interpretation.
                $cells = array_map(fn ($value) => new StringCell((string) $value, null), $values);
                foreach ([6, 7, 8] as $index) {
                    $cells[$index] = Cell::fromValue((float) $values[$index], $moneyStyle);
                }
                if ($values[1] !== '') {
                    $cells[1] = Cell::fromValue(Carbon::parse($values[1], config('app.business_timezone')), $dateStyle);
                }
                $writer->addRow(new Row($cells));
            }, $startDate, $endDate, $branchId, $cashierId);
        } finally {
            $writer->close();
        }
    }

    private function orderExportHeaders(): array
    {
        return [
            'Order #',
            'Date & Time (PHT)',
            'Branch',
            'Cashier',
            'Dining Option',
            'Table',
            'Gross Subtotal (PHP)',
            'Discount (PHP)',
            'Net Total (PHP)',
            'Payment Method',
            'Status',
        ];
    }

    private function writeOrderExportRows(
        callable $writeRow,
        ?string $startDate,
        ?string $endDate,
        ?int $branchId,
        ?int $cashierId
    ): void {
        $effectiveBranchId = $this->resolveBranchScope($branchId);

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

        $this->applyBusinessDates($query, 'orders.created_at', $startDate, $endDate);

        if ($effectiveBranchId !== null) {
            $query->where('orders.branch_id', $effectiveBranchId);
        }

        if ($cashierId) {
            $query->where('orders.cashier_id', $cashierId);
        }

        // Stream orders in 1,000-row chunks with a single batched payment lookup per chunk
        $query->chunkById(1000, function ($orders) use ($writeRow): void {
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
                    ? Carbon::parse($order->created_at)->timezone(config('app.business_timezone'))->format('Y-m-d H:i:s')
                    : '';

                $writeRow([
                    (string) $order->order_number,
                    $createdAt,
                    (string) ($order->branch_name ?? 'Global'),
                    (string) ($order->cashier_name ?? 'Staff'),
                    strtoupper(str_replace('_', ' ', (string) $order->order_type)),
                    (string) ($order->table_number ?? '—'),
                    number_format((float) $order->subtotal, 2, '.', ''),
                    number_format((float) $order->discount_amount, 2, '.', ''),
                    number_format((float) $order->total_amount, 2, '.', ''),
                    ucwords(str_replace('_', ' ', $paymentMethod)),
                    ucfirst((string) $order->status),
                ]);
            }
        }, 'orders.id', 'id');
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
