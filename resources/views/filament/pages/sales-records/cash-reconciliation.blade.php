<!-- Cash Register Drawer Reconciliation -->
<x-filament::section icon="heroicon-m-banknotes" compact>
    <x-slot name="heading">
        <span class="text-sm font-bold text-gray-900 dark:text-white">Cash Register Reconciliation</span>
    </x-slot>

    <div class="divide-y divide-gray-100 text-xs dark:divide-white/10">
        <div class="flex items-center justify-between py-2.5 gap-4">
            <span class="text-gray-600 dark:text-gray-400">Total Cash Tendered (Handed):</span>
            <span class="font-medium text-gray-900 dark:text-white font-mono whitespace-nowrap">
                ₱{{ number_format($summary['cash_reconciliation']['total_cash_tendered'] ?? 0, 2) }}
            </span>
        </div>
        <div class="flex items-center justify-between py-2.5 gap-4">
            <span class="text-gray-600 dark:text-gray-400">Total Change Given to Customers:</span>
            <span class="font-medium text-amber-600 dark:text-amber-400 font-mono whitespace-nowrap">
                - ₱{{ number_format($summary['cash_reconciliation']['total_change_given'] ?? 0, 2) }}
            </span>
        </div>
        <div
            class="flex items-center justify-between py-2.5 bg-emerald-50/50 -mx-3 px-3 rounded-md dark:bg-emerald-950/20 gap-4">
            <span class="font-bold text-emerald-800 dark:text-emerald-300">Net Physical Cash in Drawer:</span>
            <span
                class="font-black text-emerald-900 dark:text-emerald-200 font-mono text-sm whitespace-nowrap">
                ₱{{ number_format($summary['cash_reconciliation']['net_cash_in_drawer'] ?? 0, 2) }}
            </span>
        </div>
        <div class="flex items-center justify-between py-2.5 gap-4">
            <span class="text-gray-600 dark:text-gray-400">Total Digital & Card Payments:</span>
            <span class="font-bold text-blue-600 dark:text-blue-400 font-mono whitespace-nowrap">
                ₱{{ number_format($summary['cash_reconciliation']['total_digital'] ?? 0, 2) }}
            </span>
        </div>
        <div class="flex items-center justify-between py-2.5 gap-4">
            <span class="text-gray-600 dark:text-gray-400">Refunds / Void Losses:</span>
            <span
                class="font-medium font-mono whitespace-nowrap {{ ($summary['refunds_summary']['refunded_amount'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500' }}">
                ₱{{ number_format($summary['refunds_summary']['refunded_amount'] ?? 0, 2) }}
                ({{ $summary['refunds_summary']['refunded_count'] ?? 0 }} orders)
            </span>
        </div>
    </div>
</x-filament::section>

<!-- Payment Tender Reconciliation Table -->
<x-filament::section icon="heroicon-m-credit-card" compact>
    <x-slot name="heading">
        <span class="text-sm font-bold text-gray-900 dark:text-white">Payment Tender Reconciliation</span>
    </x-slot>

    @if (empty($summary['payments_breakdown']))
        <div class="py-8 text-center text-xs text-gray-400 dark:text-gray-500">
            No payment transactions recorded for the selected filter period.
        </div>
    @else
        <div class="w-full overflow-x-auto pb-0.5">
            <table class="w-full border-separate border-spacing-0 text-left text-xs">
                <thead>
                    <tr class="border-b-2 border-gray-200 dark:border-white/10">
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-left min-w-[160px]">
                            Tender Method</th>
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center min-w-[110px]">
                            Transactions</th>
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[130px]">
                            Total Collected</th>
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[90px]">
                            Share (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['payments_breakdown'] as $pay)
                        <tr
                            class="border-b border-gray-100 dark:border-white/5 last:border-b-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.03] transition-colors">
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-left font-medium text-gray-900 dark:text-white">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-2 h-2 rounded-full shrink-0"
                                        style="background-color: {{ $pay['method'] === 'cash' ? '#10b981' : '#8b5cf6' }};"></span>
                                    <span class="font-semibold">{{ $pay['label'] }}</span>
                                </div>
                            </td>
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-center text-gray-600 dark:text-gray-400 font-medium font-mono">
                                {{ number_format($pay['count']) }}
                            </td>
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right font-bold text-gray-900 dark:text-white font-mono">
                                ₱{{ number_format($pay['total'], 2) }}
                            </td>
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right">
                                <x-filament::badge color="gray" size="xs">
                                    {{ $pay['percentage'] }}%
                                </x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::section>

<!-- Cashier & Staff Performance Table -->
<x-filament::section icon="heroicon-m-user-group" compact>
    <x-slot name="heading">
        <span class="text-sm font-bold text-gray-900 dark:text-white">Cashier and Staff Performance</span>
    </x-slot>

    @if (empty($summary['cashier_performance']))
        <div class="py-8 text-center text-xs text-gray-400 dark:text-gray-500">
            No cashier activity recorded for the selected filter period.
        </div>
    @else
        <div class="w-full overflow-x-auto pb-0.5">
            <table class="w-full border-separate border-spacing-0 text-left text-xs">
                <thead>
                    <tr class="border-b-2 border-gray-200 dark:border-white/10">
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-left min-w-[160px]">
                            Staff Member</th>
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center min-w-[100px]">
                            Emp ID</th>
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center min-w-[80px]">
                            Txns</th>
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[110px]">
                            Discounts</th>
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[130px]">
                            Net Collected</th>
                        <th
                            class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[110px]">
                            Avg Ticket</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['cashier_performance'] as $staff)
                        <tr
                            class="border-b border-gray-100 dark:border-white/5 last:border-b-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.03] transition-colors">
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-left font-bold text-gray-900 dark:text-white">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-5.5 h-5.5 rounded-full bg-gray-200 dark:bg-white/10 flex items-center justify-center font-bold text-[10px] text-gray-600 dark:text-gray-300 shrink-0">
                                        {{ strtoupper(mb_substr($staff['name'] ?? 'U', 0, 1, 'UTF-8') ?: 'U') }}
                                    </div>
                                    <span class="font-bold">{{ $staff['name'] }}</span>
                                </div>
                            </td>
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-center text-gray-500 dark:text-gray-400">
                                <span
                                    class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-white/10">{{ $staff['emp_id'] ?: 'Admin' }}</span>
                            </td>
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-center font-semibold text-gray-900 dark:text-white font-mono">
                                {{ number_format($staff['orders_count']) }}
                            </td>
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right text-amber-600 dark:text-amber-400 font-medium font-mono">
                                ₱{{ number_format($staff['discounts_given'], 2) }}
                            </td>
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right font-black text-gray-900 dark:text-white font-mono">
                                ₱{{ number_format($staff['net_revenue'], 2) }}
                            </td>
                            <td
                                class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right text-gray-600 dark:text-gray-400 font-mono">
                                ₱{{ number_format($staff['avg_ticket'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::section>
