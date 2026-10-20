<x-filament-panels::page>
    <div class="flex flex-col gap-6 w-full">

        <!-- 1. Executive Timeframe & Branch Filter Bar -->
        <x-filament::section icon="heroicon-m-funnel" compact>
            <x-slot name="heading">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Smart Sales & Revenue Intelligence Scope</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Real-time revenue metrics, hourly velocity, and menu performance.</p>
                    </div>

                    <!-- Reset Trigger -->
                    <div class="flex items-center gap-2">
                        <x-filament::button
                            color="gray"
                            size="sm"
                            icon="heroicon-m-arrow-path"
                            wire:click="resetFilters"
                            tooltip="Reset filters to today">
                            Reset Filters
                        </x-filament::button>
                    </div>
                </div>
            </x-slot>

            <div class="flex flex-col gap-3 pt-1">
                <!-- Preset Buttons -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mr-2">Timeframe:</span>
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

                <!-- Custom Pickers & Branch Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-2.5 border-t border-gray-100 dark:border-white/10">
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
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Branch Scope</label>
                        <x-filament::input.wrapper prefixIcon="heroicon-m-building-storefront">
                            <x-filament::input.select wire:model.live="branchId">
                                <option value="">All Store Locations</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <!-- 2. High-Impact Executive KPI Cards Grid -->
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
                    <span class="text-[10px] text-gray-400">Menu Catalog Volume</span>
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

        <!-- 3. Operational Velocity & Rush Highlights Banner (SCRUM-175, 176) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 bg-gray-50 dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 text-xs">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-[#960D10]/15 text-[#960D10] dark:text-[#DE9A9D] shrink-0">
                    <x-filament::icon icon="heroicon-m-fire" class="w-4 h-4" />
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Peak Sales Rush</span>
                    <span class="font-bold text-gray-900 dark:text-white">{{ $hourlyDistribution['peak_hour_label'] }}</span>
                    <span class="text-[10px] text-gray-500 block font-mono">({{ $hourlyDistribution['peak_orders_count'] }} orders &bull; ₱{{ number_format($hourlyDistribution['peak_revenue'], 2) }})</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 shrink-0">
                    <x-filament::icon icon="heroicon-m-sun" class="w-4 h-4" />
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Lunch Rush (11 AM - 2 PM)</span>
                    <span class="font-bold text-gray-900 dark:text-white font-mono">₱{{ number_format($hourlyDistribution['lunch_rush']['revenue'], 2) }}</span>
                    <span class="text-[10px] text-gray-500 block font-mono">{{ $hourlyDistribution['lunch_rush']['orders_count'] }} orders handled</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 shrink-0">
                    <x-filament::icon icon="heroicon-m-moon" class="w-4 h-4" />
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Dinner Rush (5 PM - 9 PM)</span>
                    <span class="font-bold text-gray-900 dark:text-white font-mono">₱{{ number_format($hourlyDistribution['dinner_rush']['revenue'], 2) }}</span>
                    <span class="text-[10px] text-gray-500 block font-mono">{{ $hourlyDistribution['dinner_rush']['orders_count'] }} orders handled</span>
                </div>
            </div>
        </div>

        <!-- 4. Interactive Visuals 2-Column Grid (Left: Revenue Trend, Right: Peak Sales Hours) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">
            <!-- Left: Net Revenue Trend Chart (~58% width) -->
            <div class="w-full lg:col-span-7">
                @livewire(\App\Filament\Widgets\DailySalesRevenueChart::class, [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'branchId' => $branchId,
                ], key('dash-rev-chart-' . $startDate . '-' . $endDate . '-' . ($branchId ?? 'all')))
            </div>

            <!-- Right: Peak Sales Hours Histogram (~42% width) -->
            <div class="w-full lg:col-span-5">
                @livewire(\App\Filament\Widgets\PeakSalesHoursChart::class, [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'branchId' => $branchId,
                ], key('dash-peak-chart-' . $startDate . '-' . $endDate . '-' . ($branchId ?? 'all')))
            </div>
        </div>

        <!-- 5. Best-Selling Menu Items Leaderboard & Category Performance (SCRUM-167, 168, 169) -->
        <x-filament::section icon="heroicon-m-trophy" compact>
            <x-slot name="heading">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 w-full">
                    <div>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">Top 5 Best-Selling Menu Items & Leaderboard</span>
                        <p class="text-xs text-gray-400">Ranked by volume and sales performance across catalog categories</p>
                    </div>

                    <!-- Interactive Controls: Volume vs Revenue & Limits -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-lg border border-gray-200 dark:border-white/10 p-0.5 bg-gray-50 dark:bg-white/5">
                            <button type="button"
                                wire:click="setBestSellerSort('quantity')"
                                class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer {{ $bestSellerSortBy === 'quantity' ? 'bg-[#960D10] text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                                By Volume
                            </button>
                            <button type="button"
                                wire:click="setBestSellerSort('revenue')"
                                class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer {{ $bestSellerSortBy === 'revenue' ? 'bg-[#960D10] text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                                By Revenue
                            </button>
                        </div>

                        <div class="inline-flex rounded-lg border border-gray-200 dark:border-white/10 p-0.5 bg-gray-50 dark:bg-white/5">
                            <button type="button"
                                wire:click="setBestSellerLimit(5)"
                                class="px-2 py-1 text-[11px] font-semibold rounded-md transition-all cursor-pointer {{ $bestSellerLimit === 5 ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500' }}">
                                Top 5
                            </button>
                            <button type="button"
                                wire:click="setBestSellerLimit(10)"
                                class="px-2 py-1 text-[11px] font-semibold rounded-md transition-all cursor-pointer {{ $bestSellerLimit === 10 ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500' }}">
                                Top 10
                            </button>
                            <button type="button"
                                wire:click="setBestSellerLimit(20)"
                                class="px-2 py-1 text-[11px] font-semibold rounded-md transition-all cursor-pointer {{ $bestSellerLimit === 20 ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500' }}">
                                Top 20
                            </button>
                        </div>
                    </div>
                </div>
            </x-slot>

            <!-- Category Filter Chips -->
            <div class="flex flex-wrap items-center gap-1.5 pb-2.5 border-b border-gray-100 dark:border-white/10 text-xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mr-1">Category:</span>
                <button type="button"
                    wire:click="setBestSellerCategory(null)"
                    class="px-2 py-0.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ is_null($bestSellerCategoryId) ? 'bg-[#960D10] text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300' }}">
                    All Categories
                </button>
                @foreach ($categories as $cat)
                    <button type="button"
                        wire:click="setBestSellerCategory({{ $cat->id }})"
                        class="px-2 py-0.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $bestSellerCategoryId === $cat->id ? 'bg-[#960D10] text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300' }}">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>

            @if (empty($bestSellingRankings))
                <div class="py-8 text-center text-xs text-gray-400 dark:text-gray-500">
                    No product item sales recorded for the selected filter period.
                </div>
            @else
                <div class="w-full overflow-x-auto pb-0.5">
                    <table class="w-full border-separate border-spacing-0 text-left text-xs">
                        <thead>
                            <tr class="border-b-2 border-gray-200 dark:border-white/10">
                                <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center w-14">Rank</th>
                                <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-left min-w-[170px]">Menu Item</th>
                                <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-center min-w-[90px]">Qty Sold</th>
                                <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[120px]">Gross Revenue</th>
                                <th class="px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap first:pl-2 last:pr-2 text-right min-w-[90px]">Sales Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bestSellingRankings as $index => $item)
                                @php
                                    $rankNum = $item['rank'] ?? ($index + 1);
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-white/5 last:border-b-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.03] transition-colors">
                                    <td class="px-3.5 py-3 align-middle whitespace-nowrap first:pl-2 last:pr-2 text-center font-semibold text-gray-500 dark:text-gray-400">
                                        @if ($rankNum === 1)
                                            <span class="inline-flex h-6 px-1.5 items-center justify-center rounded-full text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-900/50 dark:text-amber-300">
                                                🥇 #1
                                            </span>
                                        @elseif ($rankNum === 2)
                                            <span class="inline-flex h-6 px-1.5 items-center justify-center rounded-full text-[10px] font-black bg-slate-200 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:text-slate-300">
                                                🥈 #2
                                            </span>
                                        @elseif ($rankNum === 3)
                                            <span class="inline-flex h-6 px-1.5 items-center justify-center rounded-full text-[10px] font-black bg-orange-100 text-orange-800 border border-orange-300 dark:bg-orange-950/60 dark:text-orange-300">
                                                🥉 #3
                                            </span>
                                        @else
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                                #{{ $rankNum }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3.5 py-3 align-middle first:pl-2 last:pr-2 text-left whitespace-normal">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-900 dark:text-white">{{ $item['name'] }}</span>
                                            @if (!empty($item['category_name']))
                                                <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500">{{ $item['category_name'] }}</span>
                                            @endif
                                        </div>
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

                <!-- Category Performance Distribution Breakdown -->
                @if (!empty($categoryRankings))
                    <div class="pt-4 border-t border-gray-100 dark:border-white/10">
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Category Sales Volume & Revenue Share</span>
                            <span class="text-[11px] text-gray-400 font-mono">{{ count($categoryRankings) }} categories</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                            @foreach ($categoryRankings as $catRank)
                                <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-white/5 border border-gray-200/80 dark:border-white/10 flex flex-col justify-between">
                                    <div class="flex items-center justify-between gap-1 mb-1.5">
                                        <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $catRank['category_name'] }}</span>
                                        <span class="text-[10px] font-bold text-[#960D10] font-mono">{{ $catRank['revenue_percentage'] }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-gray-200 dark:bg-white/10 rounded-full overflow-hidden mb-1.5">
                                        <div class="h-full bg-[#960D10] rounded-full" style="width: {{ $catRank['revenue_percentage'] }}%"></div>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-gray-500 font-mono">
                                        <span>{{ number_format($catRank['quantity']) }} sold</span>
                                        <span class="font-bold text-gray-800 dark:text-gray-200">₱{{ number_format($catRank['revenue'], 2) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </x-filament::section>

    </div>
</x-filament-panels::page>
