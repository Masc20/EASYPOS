<!-- 4. Periodic Sales Reports & Tax Audit (SCRUM-171, 172, 173) -->
<x-filament::section icon="heroicon-m-calendar-days" compact>
    <x-slot name="heading">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 w-full">
            <div>
                <span class="text-sm font-bold text-gray-900 dark:text-white">Periodic Sales Reports (Daily / Weekly / Monthly / Yearly)</span>
            </div>

            <!-- Periodic Controls & Exports -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Interval Tabs -->
                <div
                    class="inline-flex rounded-lg border border-gray-200 dark:border-white/10 p-0.5 bg-gray-50 dark:bg-white/5">
                    <button type="button" wire:click="setPeriodicInterval('day')"
                        class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer {{ $periodicInterval === 'day' ? 'bg-[#960D10] text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        Daily
                    </button>
                    <button type="button" wire:click="setPeriodicInterval('week')"
                        class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer {{ $periodicInterval === 'week' ? 'bg-[#960D10] text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        Weekly
                    </button>
                    <button type="button" wire:click="setPeriodicInterval('month')"
                        class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer {{ $periodicInterval === 'month' ? 'bg-[#960D10] text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        Monthly
                    </button>
                    <button type="button" wire:click="setPeriodicInterval('year')"
                        class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer {{ $periodicInterval === 'year' ? 'bg-[#960D10] text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        Yearly
                    </button>
                </div>

                <!-- Export CSV Trigger -->
                <x-filament::button color="gray" size="xs" icon="heroicon-m-arrow-down-tray"
                    wire:click="exportPeriodicCsv">
                    Export Periodic CSV
                </x-filament::button>

                <!-- Printable Report Trigger -->
                <a href="{{ route('sales-records.audit-report', ['interval' => $periodicInterval, 'startDate' => $startDate, 'endDate' => $endDate, 'branchId' => $branchId, 'cashierId' => $cashierId]) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-[#960D10] hover:bg-[#7a0a0d] transition-colors shadow-xs">
                    <x-filament::icon icon="heroicon-m-printer" class="w-3.5 h-3.5" />
                    Print Audit Report
                </a>
            </div>
        </div>
    </x-slot>

    @if (empty($periodicReport['items']))
        <div class="py-10 text-center text-xs text-gray-400 dark:text-gray-500">
            No transaction records found for the selected periodic timeframe.
        </div>
    @else
        <div class="w-full overflow-x-auto pb-0.5">
            <table class="w-full border-separate border-spacing-0 text-left text-xs">
                <thead>
                    <tr class="border-b-2 border-gray-200 dark:border-white/10">
                        <th
                            class="px-3 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2">
                            <button type="button" wire:click="sortPeriodic('period_key')"
                                class="flex items-center gap-1 cursor-pointer font-bold">
                                Period Interval
                                <x-filament::icon icon="heroicon-m-chevron-up-down"
                                    class="w-3 h-3 text-gray-400" />
                            </button>
                        </th>
                        <th
                            class="px-3 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap text-center">
                            <button type="button" wire:click="sortPeriodic('orders_count')"
                                class="flex items-center gap-1 justify-center cursor-pointer font-bold w-full">
                                Orders
                                <x-filament::icon icon="heroicon-m-chevron-up-down"
                                    class="w-3 h-3 text-gray-400" />
                            </button>
                        </th>
                        <th
                            class="px-3 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap text-right">
                            <button type="button" wire:click="sortPeriodic('gross_sales')"
                                class="flex items-center gap-1 justify-end cursor-pointer font-bold w-full">
                                Gross Sales
                                <x-filament::icon icon="heroicon-m-chevron-up-down"
                                    class="w-3 h-3 text-gray-400" />
                            </button>
                        </th>
                        <th
                            class="px-3 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap text-right">
                            <button type="button" wire:click="sortPeriodic('total_discounts')"
                                class="flex items-center gap-1 justify-end cursor-pointer font-bold w-full">
                                Discounts
                                <x-filament::icon icon="heroicon-m-chevron-up-down"
                                    class="w-3 h-3 text-gray-400" />
                            </button>
                        </th>
                        <th
                            class="px-3 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap text-right">
                            <button type="button" wire:click="sortPeriodic('net_sales')"
                                class="flex items-center gap-1 justify-end cursor-pointer font-bold w-full">
                                Net Realized
                                <x-filament::icon icon="heroicon-m-chevron-up-down"
                                    class="w-3 h-3 text-gray-400" />
                            </button>
                        </th>
                        <th
                            class="px-3 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap text-right">
                            VATable Sales
                        </th>
                        <th
                            class="px-3 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap text-right">
                            {{ \App\Support\Constants\SystemConstants::VAT_PERCENTAGE }}% VAT
                        </th>
                        <th
                            class="px-3 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap text-right">
                            VAT-Exempt
                        </th>
                        <th
                            class="px-3 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap text-right last:pr-2">
                            Avg Ticket
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($periodicReport['items'] as $pItem)
                        <tr
                            class="border-b border-gray-100 dark:border-white/5 last:border-b-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.03] transition-colors">
                            <td
                                class="px-3 py-2.5 align-middle whitespace-nowrap first:pl-2 font-bold text-gray-900 dark:text-white">
                                {{ $pItem['period_label'] }}
                            </td>
                            <td
                                class="px-3 py-2.5 align-middle whitespace-nowrap text-center font-semibold text-gray-700 dark:text-gray-300 font-mono">
                                {{ number_format($pItem['orders_count']) }}
                            </td>
                            <td
                                class="px-3 py-2.5 align-middle whitespace-nowrap text-right font-medium text-gray-600 dark:text-gray-400 font-mono">
                                ₱{{ number_format($pItem['gross_sales'], 2) }}
                            </td>
                            <td
                                class="px-3 py-2.5 align-middle whitespace-nowrap text-right font-medium text-amber-600 dark:text-amber-400 font-mono">
                                ₱{{ number_format($pItem['total_discounts'], 2) }}
                            </td>
                            <td
                                class="px-3 py-2.5 align-middle whitespace-nowrap text-right font-black text-gray-900 dark:text-white font-mono">
                                ₱{{ number_format($pItem['net_sales'], 2) }}
                            </td>
                            <td
                                class="px-3 py-2.5 align-middle whitespace-nowrap text-right font-mono text-gray-700 dark:text-gray-300">
                                ₱{{ number_format($pItem['vatable_sales'], 2) }}
                            </td>
                            <td
                                class="px-3 py-2.5 align-middle whitespace-nowrap text-right font-mono font-semibold text-[#960D10] dark:text-[#DE9A9D]">
                                ₱{{ number_format($pItem['vat_amount'], 2) }}
                            </td>
                            <td
                                class="px-3 py-2.5 align-middle whitespace-nowrap text-right font-mono text-gray-700 dark:text-gray-300">
                                ₱{{ number_format($pItem['vat_exempt_sales'], 2) }}
                            </td>
                            <td
                                class="px-3 py-2.5 align-middle whitespace-nowrap text-right font-mono text-purple-700 dark:text-purple-400 font-semibold last:pr-2">
                                ₱{{ number_format($pItem['average_order_value'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php $pTotals = $periodicReport['totals']; @endphp
                    <tr
                        class="bg-gray-50/80 dark:bg-white/5 font-black text-gray-900 dark:text-white border-t-2 border-gray-300 dark:border-white/20">
                        <td
                            class="px-3 py-3 align-middle whitespace-nowrap first:pl-2 uppercase text-[11px] tracking-wider text-gray-500 dark:text-gray-400">
                            Summary Total
                        </td>
                        <td class="px-3 py-3 align-middle whitespace-nowrap text-center font-mono">
                            {{ number_format($pTotals['orders_count']) }}
                        </td>
                        <td class="px-3 py-3 align-middle whitespace-nowrap text-right font-mono">
                            ₱{{ number_format($pTotals['gross_sales'], 2) }}
                        </td>
                        <td
                            class="px-3 py-3 align-middle whitespace-nowrap text-right font-mono text-amber-600 dark:text-amber-400">
                            -₱{{ number_format($pTotals['total_discounts'], 2) }}
                        </td>
                        <td
                            class="px-3 py-3 align-middle whitespace-nowrap text-right font-mono text-[#960D10] dark:text-[#DE9A9D] text-sm">
                            ₱{{ number_format($pTotals['net_sales'], 2) }}
                        </td>
                        <td class="px-3 py-3 align-middle whitespace-nowrap text-right font-mono">
                            ₱{{ number_format($pTotals['vatable_sales'], 2) }}
                        </td>
                        <td
                            class="px-3 py-3 align-middle whitespace-nowrap text-right font-mono text-[#960D10] dark:text-[#DE9A9D]">
                            ₱{{ number_format($pTotals['vat_amount'], 2) }}
                        </td>
                        <td class="px-3 py-3 align-middle whitespace-nowrap text-right font-mono">
                            ₱{{ number_format($pTotals['vat_exempt_sales'], 2) }}
                        </td>
                        <td
                            class="px-3 py-3 align-middle whitespace-nowrap text-right font-mono text-purple-700 dark:text-purple-400 last:pr-2">
                            ₱{{ number_format($pTotals['average_order_value'], 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</x-filament::section>
