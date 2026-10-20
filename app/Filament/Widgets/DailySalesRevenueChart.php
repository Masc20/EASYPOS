<?php

namespace App\Filament\Widgets;

use App\Domains\PointOfSale\Services\SalesAggregationService;
use Filament\Widgets\ChartWidget;

class DailySalesRevenueChart extends ChartWidget
{
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $branchId = null;
    public ?int $cashierId = null;

    protected ?string $heading = 'Revenue Trend';

    protected ?string $maxHeight = '260px';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return true;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $service = app(SalesAggregationService::class);
        $trend = $service->getDailyRevenueTrend(
            $this->startDate,
            $this->endDate,
            $this->branchId,
            $this->cashierId
        );

        return [
            'datasets' => [
                [
                    'label' => 'Net Revenue (₱)',
                    'data' => $trend['values'],
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(150, 13, 16, 0.12)',
                    'borderColor' => '#960D10',
                    'tension' => 0.35,
                    'pointBackgroundColor' => '#960D10',
                    'pointRadius' => 4,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }
}
