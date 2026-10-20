<?php

namespace App\Filament\Widgets;

use App\Domains\PointOfSale\Services\SalesAggregationService;
use Filament\Widgets\ChartWidget;

class PaymentMethodsDistributionChart extends ChartWidget
{
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $branchId = null;
    public ?int $cashierId = null;

    protected ?string $heading = 'Payment Tender Distribution';

    protected ?string $maxHeight = '260px';

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return true;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $service = app(SalesAggregationService::class);
        $dist = $service->getPaymentMethodDistribution(
            $this->startDate,
            $this->endDate,
            $this->branchId,
            $this->cashierId
        );

        return [
            'datasets' => [
                [
                    'label' => 'Amount Collected (₱)',
                    'data' => $dist['values'],
                    'backgroundColor' => [
                        '#10B981', // Emerald - Cash
                        '#3B82F6', // Blue - QR Ph / GCash / Maya
                        '#F59E0B', // Amber - Credit Card
                        '#8B5CF6', // Purple - Debit Card
                        '#EC4899', // Pink - Voucher
                    ],
                ],
            ],
            'labels' => $dist['labels'],
        ];
    }
}
