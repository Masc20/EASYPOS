<x-filament-panels::page>
    <div class="flex flex-col gap-6 w-full">

        <!-- 1. Unified Filter & Controls Bar -->
        <x-filament::section icon="heroicon-m-funnel" compact>
            <x-slot name="heading">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Sales Period and Station Filters</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Filter sales, tender, and tax data by timeframe, branch, and cashier station.</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <x-filament::button
                            color="gray"
                            size="sm"
                            icon="heroicon-m-arrow-path"
                            wire:click="resetFilters"
                            tooltip="Reset filters to today">
                            Reset
                        </x-filament::button>

                        <x-filament::button
                            color="success"
                            size="sm"
                            icon="heroicon-m-arrow-down-tray"
                            wire:click="exportCsv">
                            Export CSV Summary
                        </x-filament::button>
                    </div>
                </div>
            </x-slot>

            <div class="flex flex-col gap-3 pt-1">
                <!-- Preset Buttons -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mr-2">Period:</span>
                    <x-filament::button
                        size="xs"
                        :color="$selectedPreset === 'today' ? 'primary' : 'gray'"
                        wire:click="setPreset('today')">
                        Today
                    </x-filament::button>
                    <x-filament::button
                        size="xs"
                        :color="$selectedPreset === 'yesterday' ? 'primary' : 'gray'"
                        wire:click="setPreset('yesterday')">
                        Yesterday
                    </x-filament::button>
                    <x-filament::button
                        size="xs"
                        :color="$selectedPreset === 'this_week' ? 'primary' : 'gray'"
                        wire:click="setPreset('this_week')">
                        This Week
                    </x-filament::button>
                    <x-filament::button
                        size="xs"
                        :color="$selectedPreset === 'this_month' ? 'primary' : 'gray'"
                        wire:click="setPreset('this_month')">
                        This Month
                    </x-filament::button>
                    <x-filament::button
                        size="xs"
                        :color="$selectedPreset === 'this_year' ? 'primary' : 'gray'"
                        wire:click="setPreset('this_year')">
                        This Year
                    </x-filament::button>
                    <x-filament::button
                        size="xs"
                        :color="$selectedPreset === 'all_time' ? 'primary' : 'gray'"
                        wire:click="setPreset('all_time')">
                        All Time
                    </x-filament::button>
                </div>

                <!-- Custom Pickers Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 pt-2.5 border-t border-gray-100 dark:border-white/10">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                        <x-filament::input.wrapper prefixIcon="heroicon-m-calendar">
                            <x-filament::input type="date" wire:model.live="startDate" />
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                        <x-filament::input.wrapper prefixIcon="heroicon-m-calendar">
                            <x-filament::input type="date" wire:model.live="endDate" />
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Branch Location</label>
                        <x-filament::input.wrapper prefixIcon="heroicon-m-building-storefront">
                            <x-filament::input.select wire:model.live="branchId">
                                <option value="">All Branches</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cashier Station</label>
                        <x-filament::input.wrapper prefixIcon="heroicon-m-user">
                            <x-filament::input.select wire:model.live="cashierId">
                                <option value="">All Staff Members</option>
                                @foreach ($cashiers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->emp_id ?: 'Admin' }})</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <!-- 2. High-Impact Executive KPI Cards Grid (Visual Badges, Currency Separation, Accent Bars) -->
        @php
            $grossParts = explode('.', number_format($summary['gross_sales'], 2));
            $netParts = explode('.', number_format($summary['net_sales'], 2));
            $discParts = explode('.', number_format($summary['total_discounts'], 2));
            $cashParts = explode('.', number_format($summary['cash_reconciliation']['net_cash_in_drawer'] ?? 0, 2));
            $aovParts = explode('.', number_format($summary['average_order_value'], 2));
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3.5 w-full">
            <!-- 1. Gross Sales -->
            <div class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-gray-400 shadow-xs relative overflow-hidden box-border">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gross Sales</span>
                        <span class="flex items-center justify-center w-6 h-6 rounded-md bg-gray-100 dark:bg-white/10 text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-m-calculator" class="w-3.5 h-3.5" />
                        </span>
                    </div>
                    <div class="flex items-baseline mt-2">
                        <span class="text-xs font-bold text-gray-400 dark:text-gray-500 mr-0.5">₱</span>
                        <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white leading-tight">{{ $grossParts[0] }}</span>
                        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">.{{ $grossParts[1] }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-2.5 pt-2 border-t border-gray-100 dark:border-white/10">
                    <span class="text-[10px] text-gray-400">Menu Catalog Total</span>
                    <x-filament::badge color="gray" size="xs">Catalog</x-filament::badge>
                </div>
            </div>

            <!-- 2. Net Revenue (Hero Metric) -->
            <div class="flex flex-col justify-between rounded-xl p-4 bg-[#fdf5f5] dark:bg-[#960D10]/15 border border-[#960D10]/25 dark:border-[#960D10]/35 border-l-4 border-l-[#960D10] shadow-xs relative overflow-hidden box-border">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">Net Revenue</span>
                        <span class="flex items-center justify-center w-6 h-6 rounded-md bg-[#960D10]/15 text-[#960D10] dark:text-[#DE9A9D]">
                            <x-filament::icon icon="heroicon-m-currency-dollar" class="w-3.5 h-3.5" />
                        </span>
                    </div>
                    <div class="flex items-baseline mt-2">
                        <span class="text-xs font-bold text-[#DE9A9D] mr-0.5">₱</span>
                        <span class="text-xl font-black tracking-tight text-primary-900 dark:text-primary-100 leading-tight">{{ $netParts[0] }}</span>
                        <span class="text-xs font-semibold text-[#DE9A9D]">.{{ $netParts[1] }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-2.5 pt-2 border-t border-[#960D10]/20 dark:border-[#960D10]/30">
                    <span class="text-[10px] text-primary-700/80 dark:text-primary-400/80">Realized Income</span>
                    <x-filament::badge color="primary" size="xs">Hero Metric</x-filament::badge>
                </div>
            </div>

            <!-- 3. Discounts Granted -->
            <div class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-amber-500 shadow-xs relative overflow-hidden box-border">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Discounts</span>
                        <span class="flex items-center justify-center w-6 h-6 rounded-md bg-amber-500/15 text-amber-600 dark:text-amber-400">
                            <x-filament::icon icon="heroicon-m-tag" class="w-3.5 h-3.5" />
                        </span>
                    </div>
                    <div class="flex items-baseline mt-2">
                        <span class="text-xs font-bold text-amber-500 mr-0.5">-₱</span>
                        <span class="text-xl font-black tracking-tight text-amber-800 dark:text-amber-300 leading-tight">{{ $discParts[0] }}</span>
                        <span class="text-xs font-semibold text-amber-500">.{{ $discParts[1] }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-2.5 pt-2 border-t border-gray-100 dark:border-white/10">
                    <span class="text-[10px] text-amber-700/80 dark:text-amber-400/80">Senior / PWD / Promo</span>
                    <x-filament::badge color="warning" size="xs">Deductions</x-filament::badge>
                </div>
            </div>

            <!-- 4. Cash in Drawer -->
            <div class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-emerald-500 shadow-xs relative overflow-hidden box-border">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Cash in Drawer</span>
                        <span class="flex items-center justify-center w-6 h-6 rounded-md bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                            <x-filament::icon icon="heroicon-m-banknotes" class="w-3.5 h-3.5" />
                        </span>
                    </div>
                    <div class="flex items-baseline mt-2">
                        <span class="text-xs font-bold text-emerald-500 mr-0.5">₱</span>
                        <span class="text-xl font-black tracking-tight text-emerald-800 dark:text-emerald-300 leading-tight">{{ $cashParts[0] }}</span>
                        <span class="text-xs font-semibold text-emerald-500">.{{ $cashParts[1] }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-2.5 pt-2 border-t border-gray-100 dark:border-white/10">
                    <span class="text-[10px] text-emerald-700/80 dark:text-emerald-400/80">Physical Cash</span>
                    <x-filament::badge color="success" size="xs">In Register</x-filament::badge>
                </div>
            </div>

            <!-- 5. Completed Orders -->
            <div class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-blue-500 shadow-xs relative overflow-hidden box-border">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Paid Orders</span>
                        <span class="flex items-center justify-center w-6 h-6 rounded-md bg-blue-500/15 text-blue-600 dark:text-blue-400">
                            <x-filament::icon icon="heroicon-m-shopping-bag" class="w-3.5 h-3.5" />
                        </span>
                    </div>
                    <div class="flex items-baseline mt-2">
                        <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white leading-tight">{{ number_format($summary['orders_count']) }}</span>
                        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 ml-1">orders</span>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-2.5 pt-2 border-t border-gray-100 dark:border-white/10">
                    <span class="text-[10px] text-gray-400">Transactions Handled</span>
                    <x-filament::badge color="info" size="xs">Completed</x-filament::badge>
                </div>
            </div>

            <!-- 6. Average Ticket (AOV) -->
            <div class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-purple-500 shadow-xs relative overflow-hidden box-border">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Ticket (AOV)</span>
                        <span class="flex items-center justify-center w-6 h-6 rounded-md bg-purple-500/15 text-purple-600 dark:text-purple-400">
                            <x-filament::icon icon="heroicon-m-arrow-trending-up" class="w-3.5 h-3.5" />
                        </span>
                    </div>
                    <div class="flex items-baseline mt-2">
                        <span class="text-xs font-bold text-gray-400 dark:text-gray-500 mr-0.5">₱</span>
                        <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white leading-tight">{{ $aovParts[0] }}</span>
                        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">.{{ $aovParts[1] }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-2.5 pt-2 border-t border-gray-100 dark:border-white/10">
                    <span class="text-[10px] text-gray-400">Spend per Customer</span>
                    <x-filament::badge color="gray" size="xs">Basket Size</x-filament::badge>
                </div>
            </div>
        </div>

        <!-- 3. Responsive 2-Column Dashboard Workspace (Left: Operational Velocity, Right: Audit & Tax) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">

            <!-- LEFT COLUMN (~58% width): Revenue Trend, Top Products, Cashier Performance -->
            <div class="flex flex-col gap-6 w-full lg:col-span-7">

                <!-- Daily Net Revenue Trend Chart -->
                <div class="w-full">
                    @livewire(\App\Filament\Widgets\DailySalesRevenueChart::class, [
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'branchId' => $branchId,
                        'cashierId' => $cashierId,
                    ], key('rev-chart-' . $startDate . '-' . $endDate . '-' . ($branchId ?? 'all') . '-' . ($cashierId ?? 'all')))
                </div>

                <!-- Top 5 Best-Selling Menu Items Table -->
                <x-filament::section icon="heroicon-m-fire" compact>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">Top 5 Best-Selling Menu Items</span>
                            <span class="text-xs text-gray-400">By quantity sold</span>
                        </div>
                    </x-slot>

                    @if (empty($summary['top_selling_items']))
                        <div class="py-8 text-center text-xs text-gray-400 dark:text-gray-500">
                            No product item sales recorded for the selected filter period.
                        </div>
                    @else
                        <div class="w-full overflow-x-auto pb-0.5">
                            <table class="w-full border-separate border-spacing-0 text-left text-xs">
                                <thead>
                                    <tr class="border-b-2 border-gray-200 dark:border-white/10">
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center w-14">Rank</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-left min-w-[150px]">Menu Item</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center min-w-[90px]">Qty Sold</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[120px]">Gross Revenue</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[90px]">Sales Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($summary['top_selling_items'] as $index => $item)
                                        <tr class="border-b border-gray-100 dark:border-white/5 last:border-b-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.03] transition-colors">
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-center font-semibold text-gray-500 dark:text-gray-400">
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold {{ $index === 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' }}">
                                                    #{{ $index + 1 }}
                                                </span>
                                            </td>
                                            <td class="px-3.5 py-3 align-middle first:pl-2 last:pr-2 text-left font-bold text-gray-900 dark:text-white whitespace-normal">
                                                {{ $item['name'] }}
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-center font-semibold text-gray-700 dark:text-gray-300 font-mono">
                                                {{ number_format($item['quantity']) }}
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right font-bold text-gray-900 dark:text-white font-mono">
                                                ₱{{ number_format($item['revenue'], 2) }}
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right">
                                                <x-filament::badge color="primary" size="xs">
                                                    {{ $item['percentage'] }}%
                                                </x-filament::badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>

                <!-- Payment Tender Reconciliation Table -->
                <x-filament::section icon="heroicon-m-credit-card" compact>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">Payment Tender Reconciliation</span>
                            <span class="text-xs text-gray-400">Cash vs Electronic</span>
                        </div>
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
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-left min-w-[160px]">Tender Method</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center min-w-[110px]">Transactions</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[130px]">Total Collected</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[90px]">Share (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($summary['payments_breakdown'] as $pay)
                                        <tr class="border-b border-gray-100 dark:border-white/5 last:border-b-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.03] transition-colors">
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-left font-medium text-gray-900 dark:text-white">
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-block w-2 h-2 rounded-full shrink-0" style="background-color: {{ $pay['method'] === 'cash' ? '#10b981' : '#8b5cf6' }};"></span>
                                                    <span class="font-semibold">{{ $pay['label'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-center text-gray-600 dark:text-gray-400 font-medium font-mono">
                                                {{ number_format($pay['count']) }}
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right font-bold text-gray-900 dark:text-white font-mono">
                                                ₱{{ number_format($pay['total'], 2) }}
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right">
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
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">Cashier and Staff Performance</span>
                            <span class="text-xs text-gray-400">Terminal activity</span>
                        </div>
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
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-left min-w-[160px]">Staff Member</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center min-w-[100px]">Emp ID</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center min-w-[80px]">Txns</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[110px]">Discounts</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[130px]">Net Collected</th>
                                        <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[110px]">Avg Ticket</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($summary['cashier_performance'] as $staff)
                                        <tr class="border-b border-gray-100 dark:border-white/5 last:border-b-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.03] transition-colors">
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-left font-bold text-gray-900 dark:text-white">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-5.5 h-5.5 rounded-full bg-gray-200 dark:bg-white/10 flex items-center justify-center font-bold text-[10px] text-gray-600 dark:text-gray-300 shrink-0">
                                                        {{ strtoupper(substr($staff['name'], 0, 1)) }}
                                                    </div>
                                                    <span class="font-bold">{{ $staff['name'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-center text-gray-500 dark:text-gray-400">
                                                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-white/10">{{ $staff['emp_id'] ?: 'Admin' }}</span>
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-center font-semibold text-gray-900 dark:text-white font-mono">
                                                {{ number_format($staff['orders_count']) }}
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right text-amber-600 dark:text-amber-400 font-medium font-mono">
                                                ₱{{ number_format($staff['discounts_given'], 2) }}
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right font-black text-gray-900 dark:text-white font-mono">
                                                ₱{{ number_format($staff['net_revenue'], 2) }}
                                            </td>
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-right text-gray-600 dark:text-gray-400 font-mono">
                                                ₱{{ number_format($staff['avg_ticket'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>

            </div>

            <!-- RIGHT COLUMN (~42% width): Tender Doughnut Chart, Drawer Balancing, Tax Compliance, Dining Modes -->
            <div class="flex flex-col gap-6 w-full lg:col-span-5">

                <!-- Doughnut Chart: Payment Tender Breakdown -->
                <div class="w-full">
                    @livewire(\App\Filament\Widgets\PaymentMethodsDistributionChart::class, [
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'branchId' => $branchId,
                        'cashierId' => $cashierId,
                    ], key('pay-chart-' . $startDate . '-' . $endDate . '-' . ($branchId ?? 'all') . '-' . ($cashierId ?? 'all')))
                </div>

                <!-- Cash Drawer & Register Reconciliation -->
                <x-filament::section icon="heroicon-m-banknotes" compact>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">Cash Register Reconciliation</span>
                            <x-filament::badge color="success" size="xs">Drawer Audit</x-filament::badge>
                        </div>
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
                        <div class="flex items-center justify-between py-2.5 bg-emerald-50/50 -mx-3 px-3 rounded-md dark:bg-emerald-950/20 gap-4">
                            <span class="font-bold text-emerald-800 dark:text-emerald-300">Net Physical Cash in Drawer:</span>
                            <span class="font-black text-emerald-900 dark:text-emerald-200 font-mono text-sm whitespace-nowrap">
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
                            <span class="font-medium font-mono whitespace-nowrap {{ ($summary['refunds_summary']['refunded_amount'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500' }}">
                                ₱{{ number_format($summary['refunds_summary']['refunded_amount'] ?? 0, 2) }}
                                ({{ $summary['refunds_summary']['refunded_count'] ?? 0 }} orders)
                            </span>
                        </div>
                    </div>
                </x-filament::section>

                <!-- Philippine BIR Tax Compliance Breakdown -->
                <x-filament::section icon="heroicon-m-document-check" compact>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">BIR Tax Compliance Breakdown</span>
                            <x-filament::badge color="info" size="xs">RR 16-2005</x-filament::badge>
                        </div>
                    </x-slot>

                    <div class="divide-y divide-gray-100 text-xs dark:divide-white/10">
                        <div class="flex items-center justify-between py-2.5 gap-4">
                            <span class="text-gray-600 dark:text-gray-400">VATable Sales (12% Base):</span>
                            <span class="font-bold text-gray-900 dark:text-white font-mono whitespace-nowrap">
                                ₱{{ number_format($summary['tax_summary']['vatable_sales'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2.5 gap-4">
                            <span class="text-gray-600 dark:text-gray-400">VAT Output Amount (12%):</span>
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
                        <div class="flex items-center justify-between py-2.5 bg-gray-50/50 -mx-3 px-3 rounded-b-lg dark:bg-white/5 gap-4">
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
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">Dining Mode Breakdown</span>
                            <span class="text-xs text-gray-400">Order distribution</span>
                        </div>
                    </x-slot>

                    @php
                        $totalTypesCount = array_sum($summary['order_types_breakdown']);
                        $dineInCount = $summary['order_types_breakdown']['dine_in'] ?? 0;
                        $takeoutCount = $summary['order_types_breakdown']['takeout'] ?? 0;
                        $deliveryCount = $summary['order_types_breakdown']['delivery'] ?? 0;
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
                                    <span class="text-gray-400 font-normal">({{ $totalTypesCount > 0 ? round(($dineInCount / $totalTypesCount) * 100, 1) : 0 }}%)</span>
                                </span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                                <div class="h-full bg-[#960D10] rounded-full" style="width: {{ $totalTypesCount > 0 ? ($dineInCount / $totalTypesCount) * 100 : 0 }}%"></div>
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
                                    <span class="text-gray-400 font-normal">({{ $totalTypesCount > 0 ? round(($takeoutCount / $totalTypesCount) * 100, 1) : 0 }}%)</span>
                                </span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                                <div class="h-full bg-amber-500 rounded-full" style="width: {{ $totalTypesCount > 0 ? ($takeoutCount / $totalTypesCount) * 100 : 0 }}%"></div>
                            </div>
                        </div>

                        <!-- Delivery -->
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Delivery
                                </span>
                                <span class="font-bold text-gray-900 dark:text-white">
                                    {{ $deliveryCount }} orders
                                    <span class="text-gray-400 font-normal">({{ $totalTypesCount > 0 ? round(($deliveryCount / $totalTypesCount) * 100, 1) : 0 }}%)</span>
                                </span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $totalTypesCount > 0 ? ($deliveryCount / $totalTypesCount) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                    </div>
                </x-filament::section>

            </div>

        </div>

    </div>
</x-filament-panels::page>
