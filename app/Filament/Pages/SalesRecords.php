<?php

namespace App\Filament\Pages;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class SalesRecords extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Sales & Transactions';

    protected static ?string $navigationLabel = 'Sales Records';

    protected static ?string $title = 'Sales Records & Financial Summaries';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.sales-records';

    public string $startDate = '';
    public string $endDate = '';
    public string $selectedPreset = 'today';
    public ?int $branchId = null;
    public ?int $cashierId = null;

    public function mount(): void
    {
        $this->setPreset('today');
    }

    public function setPreset(string $preset): void
    {
        $this->selectedPreset = $preset;

        switch ($preset) {
            case 'today':
                $this->startDate = Carbon::today()->toDateString();
                $this->endDate = Carbon::today()->toDateString();
                break;
            case 'yesterday':
                $this->startDate = Carbon::yesterday()->toDateString();
                $this->endDate = Carbon::yesterday()->toDateString();
                break;
            case 'this_week':
                $this->startDate = Carbon::now()->startOfWeek()->toDateString();
                $this->endDate = Carbon::now()->endOfWeek()->toDateString();
                break;
            case 'this_month':
                $this->startDate = Carbon::now()->startOfMonth()->toDateString();
                $this->endDate = Carbon::now()->endOfMonth()->toDateString();
                break;
            case 'this_year':
                $this->startDate = Carbon::now()->startOfYear()->toDateString();
                $this->endDate = Carbon::now()->endOfYear()->toDateString();
                break;
            case 'all_time':
                $this->startDate = '';
                $this->endDate = '';
                break;
        }
    }

    public function resetFilters(): void
    {
        $this->branchId = null;
        $this->cashierId = null;
        $this->setPreset('today');
    }

    public function exportCsv(SalesAggregationService $service): StreamedResponse
    {
        $csvData = $service->exportCsv(
            $this->startDate,
            $this->endDate,
            $this->branchId,
            $this->cashierId
        );

        $filename = 'sales-records-' . $this->startDate . '-to-' . $this->endDate . '.csv';

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function getViewData(): array
    {
        $service = app(SalesAggregationService::class);
        $summary = $service->getSummary(
            $this->startDate,
            $this->endDate,
            $this->branchId,
            $this->cashierId
        );

        $branches = Branch::query()->orderBy('name')->get();
        $cashiers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['cashier', 'branch-manager', 'owner', 'super-admin']))
            ->orderBy('name')
            ->get();

        return [
            'summary' => $summary,
            'branches' => $branches,
            'cashiers' => $cashiers,
        ];
    }
}
