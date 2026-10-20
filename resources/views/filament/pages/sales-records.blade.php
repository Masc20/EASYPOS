<x-filament-panels::page>
    <!-- Filter Toolbar -->
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <!-- Preset Quick Selectors -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mr-1">Period:</span>
                <button type="button" wire:click="setPreset('today')"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $selectedPreset === 'today' ? 'bg-primary-600 text-white dark:bg-primary-500' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                    Today
                </button>
                <button type="button" wire:click="setPreset('yesterday')"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $selectedPreset === 'yesterday' ? 'bg-primary-600 text-white dark:bg-primary-500' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                    Yesterday
                </button>
                <button type="button" wire:click="setPreset('this_week')"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $selectedPreset === 'this_week' ? 'bg-primary-600 text-white dark:bg-primary-500' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                    This Week
                </button>
                <button type="button" wire:click="setPreset('this_month')"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $selectedPreset === 'this_month' ? 'bg-primary-600 text-white dark:bg-primary-500' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                    This Month
                </button>
            </div>

            <!-- Export Button -->
            <button type="button" wire:click="exportCsv"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export CSV Summary
            </button>
        </div>

        <!-- Custom Date Range & Filters Row -->
        <div class="mt-4 grid grid-cols-1 gap-4 pt-4 border-t border-gray-100 sm:grid-cols-2 lg:grid-cols-4 dark:border-gray-800">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                <input type="date" wire:model.live="startDate"
                    class="w-full rounded-lg border-gray-300 text-xs shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                <input type="date" wire:model.live="endDate"
                    class="w-full rounded-lg border-gray-300 text-xs shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by Branch</label>
                <select wire:model.live="branchId"
                    class="w-full rounded-lg border-gray-300 text-xs shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="">All Branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by Cashier / Staff</label>
                <select wire:model.live="cashierId"
                    class="w-full rounded-lg border-gray-300 text-xs shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="">All Staff Members</option>
                    @foreach ($cashiers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->emp_id ?: 'Admin' }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <!-- Gross Sales -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gross Sales</span>
            <div class="mt-2 text-2xl font-black text-gray-900 dark:text-white">
                ₱{{ number_format($summary['gross_sales'], 2) }}
            </div>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Total menu value rung up</p>
        </div>

        <!-- Net Revenue -->
        <div class="rounded-xl border border-primary-200 bg-primary-50/50 p-5 shadow-sm dark:border-primary-900/50 dark:bg-primary-950/20">
            <span class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Net Revenue</span>
            <div class="mt-2 text-2xl font-black text-primary-800 dark:text-primary-200">
                ₱{{ number_format($summary['net_sales'], 2) }}
            </div>
            <p class="mt-1 text-xs text-primary-600/80 dark:text-primary-400/80">Actual revenue after discounts</p>
        </div>

        <!-- Total Discounts -->
        <div class="rounded-xl border border-amber-200 bg-amber-50/40 p-5 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20">
            <span class="text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">Total Discounts</span>
            <div class="mt-2 text-2xl font-black text-amber-800 dark:text-amber-300">
                ₱{{ number_format($summary['total_discounts'], 2) }}
            </div>
            <p class="mt-1 text-xs text-amber-600/80 dark:text-amber-400/80">Senior / PWD / promos granted</p>
        </div>

        <!-- Orders Count -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Completed Orders</span>
            <div class="mt-2 text-2xl font-black text-gray-900 dark:text-white">
                {{ number_format($summary['orders_count']) }}
            </div>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Paid customer transactions</p>
        </div>

        <!-- Average Ticket Size -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Ticket (AOV)</span>
            <div class="mt-2 text-2xl font-black text-gray-900 dark:text-white">
                ₱{{ number_format($summary['average_order_value'], 2) }}
            </div>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Average spend per customer</p>
        </div>
    </div>

    <!-- Detailed Section: Payment Methods & Dining Breakdown -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Payment Methods Breakdown (2 Cols) -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Payment Tender Reconciliation</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Cash vs Electronic Payment method breakdown</p>
                </div>
            </div>

            @if (empty($summary['payments_breakdown']))
                <div class="py-10 text-center text-xs text-gray-400 dark:text-gray-500">
                    No payment transactions recorded for the selected date range.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <th class="pb-3 font-semibold">Payment Method</th>
                                <th class="pb-3 text-center font-semibold">Transactions</th>
                                <th class="pb-3 text-right font-semibold">Total Amount</th>
                                <th class="pb-3 text-right font-semibold">Share (%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($summary['payments_breakdown'] as $pay)
                                <tr>
                                    <td class="py-3 font-medium text-gray-900 dark:text-white">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full {{ $pay['method'] === 'cash' ? 'bg-emerald-500' : 'bg-primary-500' }}"></span>
                                            {{ $pay['label'] }}
                                        </div>
                                    </td>
                                    <td class="py-3 text-center text-gray-600 dark:text-gray-400">
                                        {{ $pay['count'] }}
                                    </td>
                                    <td class="py-3 text-right font-bold text-gray-900 dark:text-white">
                                        ₱{{ number_format($pay['total'], 2) }}
                                    </td>
                                    <td class="py-3 text-right text-gray-500 dark:text-gray-400">
                                        <span class="inline-block rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $pay['percentage'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Dining Option Breakdown (1 Col) -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-bold text-gray-900 dark:text-white">Dining Mode Breakdown</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Volume distribution by order type</p>

            <div class="space-y-4">
                @php
                    $totalTypesCount = array_sum($summary['order_types_breakdown']);
                    $dineInCount = $summary['order_types_breakdown']['dine_in'] ?? 0;
                    $takeoutCount = $summary['order_types_breakdown']['takeout'] ?? 0;
                    $deliveryCount = $summary['order_types_breakdown']['delivery'] ?? 0;
                @endphp

                <!-- Dine In -->
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Dine-In</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $dineInCount }} orders</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div class="h-full bg-primary-600 rounded-full" style="width: {{ $totalTypesCount > 0 ? ($dineInCount / $totalTypesCount) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <!-- Takeout -->
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Takeout / To-Go</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $takeoutCount }} orders</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div class="h-full bg-amber-500 rounded-full" style="width: {{ $totalTypesCount > 0 ? ($takeoutCount / $totalTypesCount) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <!-- Delivery -->
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Delivery</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $deliveryCount }} orders</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $totalTypesCount > 0 ? ($deliveryCount / $totalTypesCount) * 100 : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
