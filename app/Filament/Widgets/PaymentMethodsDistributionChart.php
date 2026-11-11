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
        $user = auth()->user();
        $effectiveBranchId = $user ? $user->effectiveBranchId($this->branchId) : $this->branchId;
        $cashierId = $this->cashierId;

        if ($user && $user->isBranchScoped() && $cashierId) {
            $cashierBelongs = \App\Domains\Identity\Models\User::where('id', $cashierId)->where('branch_id', $effectiveBranchId)->exists();
            if (! $cashierBelongs) {
                $cashierId = null;
            }
        }

        $service = app(SalesAggregationService::class);
        $dist = $service->getPaymentMethodDistribution(
            $this->startDate,
            $this->endDate,
            $effectiveBranchId,
            $cashierId
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
