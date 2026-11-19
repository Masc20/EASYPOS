<?php

namespace App\Filament\Widgets;

use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use Carbon\Carbon;
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
        $user = auth()->user();
        $effectiveBranchId = $user ? $user->effectiveBranchId($this->branchId) : $this->branchId;
        $cashierId = $this->cashierId;

        $service = app(SalesAggregationService::class);

        if ($user && $user->isBranchScoped() && $effectiveBranchId < 0) {
            $fallbackDate = $service->parseSafeDate($this->startDate) ?? now(config('app.business_timezone'));

            return [
                'datasets' => [
                    [
                        'label' => 'Net Revenue (₱)',
                        'data' => [0.00],
                        'fill' => 'start',
                        'backgroundColor' => 'rgba(150, 13, 16, 0.12)',
                        'borderColor' => '#960D10',
                        'tension' => 0.35,
                        'pointBackgroundColor' => '#960D10',
                        'pointRadius' => 4,
                    ],
                ],
                'labels' => [$fallbackDate->format('M d')],
            ];
        }

        if ($user && $user->isBranchScoped() && $cashierId) {
            $cashierBelongs = User::where('id', $cashierId)
                ->where('branch_id', $effectiveBranchId)
                ->exists();
            if (! $cashierBelongs) {
                $cashierId = null;
            }
        }
        $trend = $service->getDailyRevenueTrend(
            $this->startDate,
            $this->endDate,
            $effectiveBranchId,
            $cashierId
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
