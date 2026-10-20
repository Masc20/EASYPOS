<?php

namespace App\Filament\Widgets;

use App\Domains\PointOfSale\Services\SalesAggregationService;
use Filament\Widgets\ChartWidget;

class PeakSalesHoursChart extends ChartWidget
{
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $branchId = null;
    public ?int $cashierId = null;

    protected ?string $heading = 'Peak Sales Hours & Hourly Activity';

    protected ?string $maxHeight = '260px';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return true;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $service = app(SalesAggregationService::class);
        $distribution = $service->getHourlySalesDistribution(
            $this->startDate,
            $this->endDate,
            $this->branchId,
            $this->cashierId
        );

        $labels = [];
        $data = [];
        $backgroundColors = [];
        $borderColors = [];

        foreach ($distribution['hours'] as $hourData) {
            $labels[] = $hourData['label'];
            $data[] = $hourData['orders_count'];

            if ($hourData['is_peak']) {
                $backgroundColors[] = '#960D10';
                $borderColors[] = '#740A0C';
            } else {
                $backgroundColors[] = 'rgba(150, 13, 16, 0.28)';
                $borderColors[] = 'rgba(150, 13, 16, 0.45)';
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Transactions Completed',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
