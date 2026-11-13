<!-- Doughnut Chart: Payment Tender Breakdown -->
<div class="w-full">
    @livewire(
        \App\Filament\Widgets\PaymentMethodsDistributionChart::class,
        [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'branchId' => $branchId,
            'cashierId' => $cashierId,
        ],
        key('pay-chart-' . $startDate . '-' . $endDate . '-' . ($branchId ?? 'all') . '-' . ($cashierId ?? 'all'))
    )
</div>

<!-- Philippine BIR Tax Compliance Breakdown -->
<x-filament::section icon="heroicon-m-document-check" compact>
    <x-slot name="heading">
        <span class="text-sm font-bold text-gray-900 dark:text-white">BIR Tax Compliance Breakdown</span>
    </x-slot>

    <div class="divide-y divide-gray-100 text-xs dark:divide-white/10">
        <div class="flex items-center justify-between py-2.5 gap-4">
            <span class="text-gray-600 dark:text-gray-400">VATable Sales ({{ \App\Support\Constants\SystemConstants::VAT_PERCENTAGE }}% Base):</span>
            <span class="font-bold text-gray-900 dark:text-white font-mono whitespace-nowrap">
                ₱{{ number_format($summary['tax_summary']['vatable_sales'] ?? 0, 2) }}
            </span>
        </div>
        <div class="flex items-center justify-between py-2.5 gap-4">
            <span class="text-gray-600 dark:text-gray-400">VAT Output Amount ({{ \App\Support\Constants\SystemConstants::VAT_PERCENTAGE }}%):</span>
            <span class="font-bold text-primary-600 dark:text-primary-400 font-mono whitespace-nowrap">
                ₱{{ number_format($summary['tax_summary']['vat_amount'] ?? 0, 2) }}
            </span>
        </div>
        <div class="flex items-center justify-between py-2.5 gap-4">
            <span class="text-gray-600 dark:text-gray-400">VAT-Exempt Sales (Senior/PWD):</span>
            <span class="font-bold text-gray-900 dark:text-white font-mono whitespace-nowrap">
                ₱{{ number_format($summary['tax_summary']['vat_exempt_sales'] ?? 0, 2) }}
            </span>
        </div>
        <div class="flex items-center justify-between py-2.5 gap-4">
            <span class="text-gray-600 dark:text-gray-400">Zero-Rated Sales:</span>
            <span class="font-bold text-gray-900 dark:text-white font-mono whitespace-nowrap">
                ₱{{ number_format($summary['tax_summary']['zero_rated_sales'] ?? 0, 2) }}
            </span>
        </div>
        <div
            class="flex items-center justify-between py-2.5 bg-gray-50/50 -mx-3 px-3 rounded-b-lg dark:bg-white/5 gap-4">
            <span class="font-bold text-gray-700 dark:text-gray-300">Total Taxable Sales Base:</span>
            <span class="font-black text-gray-900 dark:text-white font-mono text-sm whitespace-nowrap">
                ₱{{ number_format(($summary['tax_summary']['vatable_sales'] ?? 0) + ($summary['tax_summary']['vat_exempt_sales'] ?? 0), 2) }}
            </span>
        </div>
    </div>
</x-filament::section>

<!-- Dining Mode Breakdown -->
<x-filament::section icon="heroicon-m-clipboard-document-list" compact>
    <x-slot name="heading">
        <span class="text-sm font-bold text-gray-900 dark:text-white">Dining Mode Breakdown</span>
    </x-slot>

    @php
        $dineInCount = $summary['order_types_breakdown']['dine_in'] ?? 0;
        $takeoutCount = $summary['order_types_breakdown']['takeout'] ?? 0;
        $totalTypesCount = $dineInCount + $takeoutCount;
    @endphp

    <div class="flex flex-col gap-3.5 pt-1">
        <!-- Dine In -->
        <div>
            <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-[#960D10]"></span> Dine-In
                </span>
                <span class="font-bold text-gray-900 dark:text-white">
                    {{ $dineInCount }} orders
                    <span
                        class="text-gray-400 font-normal">({{ $totalTypesCount > 0 ? round(($dineInCount / $totalTypesCount) * 100, 1) : 0 }}%)</span>
                </span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                <div class="h-full bg-[#960D10] rounded-full"
                    style="width: {{ $totalTypesCount > 0 ? ($dineInCount / $totalTypesCount) * 100 : 0 }}%">
                </div>
            </div>
        </div>

        <!-- Takeout -->
        <div>
            <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span> Takeout / To-Go
                </span>
                <span class="font-bold text-gray-900 dark:text-white">
                    {{ $takeoutCount }} orders
                    <span
                        class="text-gray-400 font-normal">({{ $totalTypesCount > 0 ? round(($takeoutCount / $totalTypesCount) * 100, 1) : 0 }}%)</span>
                </span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                <div class="h-full bg-amber-500 rounded-full"
                    style="width: {{ $totalTypesCount > 0 ? ($takeoutCount / $totalTypesCount) * 100 : 0 }}%">
                </div>
            </div>
        </div>
    </div>
</x-filament::section>
