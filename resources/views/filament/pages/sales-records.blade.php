<x-filament-panels::page>
    <!-- Scoped Styles to Guarantee Cockpit Grid Layout in Filament Admin -->
    <style>
        .sales-cockpit-grid {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 24px !important;
            align-items: start !important;
            width: 100% !important;
        }
        @media (min-width: 1024px) {
            .sales-cockpit-grid {
                grid-template-columns: 7fr 5fr !important;
            }
        }
        .sales-kpi-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 14px !important;
            width: 100% !important;
        }
        @media (min-width: 640px) {
            .sales-kpi-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }
        @media (min-width: 1280px) {
            .sales-kpi-grid {
                grid-template-columns: repeat(6, 1fr) !important;
            }
        }
        .sales-filters-grid {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 14px !important;
            width: 100% !important;
        }
        @media (min-width: 640px) {
            .sales-filters-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media (min-width: 1024px) {
            .sales-filters-grid {
                grid-template-columns: repeat(4, 1fr) !important;
            }
        }
        .sales-kpi-card {
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
            border-radius: 12px !important;
            padding: 16px !important;
            background-color: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05) !important;
            position: relative !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
        }
        .dark .sales-kpi-card {
            background-color: #111827 !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        .kpi-accent-gross {
            border-left: 4px solid #9ca3af !important;
        }
        .kpi-accent-net {
            border-left: 4px solid #960D10 !important;
            background-color: #fdf5f5 !important;
            border-color: rgba(150, 13, 16, 0.25) !important;
        }
        .dark .kpi-accent-net {
            background-color: rgba(150, 13, 16, 0.15) !important;
            border-color: rgba(150, 13, 16, 0.35) !important;
        }
        .kpi-accent-discount {
            border-left: 4px solid #f59e0b !important;
        }
        .kpi-accent-cash {
            border-left: 4px solid #10b981 !important;
        }
        .kpi-accent-orders {
            border-left: 4px solid #3b82f6 !important;
        }
        .kpi-accent-aov {
            border-left: 4px solid #8b5cf6 !important;
        }
    </style>

    <div style="display: flex; flex-direction: column; gap: 24px; width: 100%;">

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

            <div style="display: flex; flex-direction: column; gap: 12px; padding-top: 4px;">
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
                <div class="sales-filters-grid" style="padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.06);">
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

        <div class="sales-kpi-grid">
            <!-- 1. Gross Sales -->
            <div class="sales-kpi-card kpi-accent-gross">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gross Sales</span>
                        <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background-color: rgba(156, 163, 175, 0.15); color: #6b7280;">
                            <x-filament::icon icon="heroicon-m-calculator" style="width: 14px; height: 14px;" />
                        </span>
                    </div>
                    <div style="display: flex; align-items: baseline; margin-top: 8px;">
                        <span style="font-size: 13px; font-weight: 700; color: #9ca3af; margin-right: 2px;">₱</span>
                        <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white" style="line-height: 1.1;">{{ $grossParts[0] }}</span>
                        <span style="font-size: 13px; font-weight: 600; color: #9ca3af;">.{{ $grossParts[1] }}</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.06);">
                    <span class="text-[10px] text-gray-400">Menu Catalog Total</span>
                    <x-filament::badge color="gray" size="xs">Catalog</x-filament::badge>
                </div>
            </div>

            <!-- 2. Net Revenue (Hero Metric) -->
            <div class="sales-kpi-card kpi-accent-net">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">Net Revenue</span>
                        <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background-color: rgba(150, 13, 16, 0.15); color: #960D10;">
                            <x-filament::icon icon="heroicon-m-currency-dollar" style="width: 14px; height: 14px;" />
                        </span>
                    </div>
                    <div style="display: flex; align-items: baseline; margin-top: 8px;">
                        <span style="font-size: 13px; font-weight: 700; color: #DE9A9D; margin-right: 2px;">₱</span>
                        <span class="text-xl font-black tracking-tight text-primary-900 dark:text-primary-100" style="line-height: 1.1;">{{ $netParts[0] }}</span>
                        <span style="font-size: 13px; font-weight: 600; color: #DE9A9D;">.{{ $netParts[1] }}</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(150, 13, 16, 0.15);">
                    <span class="text-[10px] text-primary-700/80 dark:text-primary-400/80">Realized Income</span>
                    <x-filament::badge color="primary" size="xs">Hero Metric</x-filament::badge>
                </div>
            </div>

            <!-- 3. Discounts Granted -->
            <div class="sales-kpi-card kpi-accent-discount">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Discounts</span>
                        <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background-color: rgba(245, 158, 11, 0.15); color: #d97706;">
                            <x-filament::icon icon="heroicon-m-tag" style="width: 14px; height: 14px;" />
                        </span>
                    </div>
                    <div style="display: flex; align-items: baseline; margin-top: 8px;">
                        <span style="font-size: 13px; font-weight: 700; color: #f59e0b; margin-right: 2px;">-₱</span>
                        <span class="text-xl font-black tracking-tight text-amber-800 dark:text-amber-300" style="line-height: 1.1;">{{ $discParts[0] }}</span>
                        <span style="font-size: 13px; font-weight: 600; color: #f59e0b;">.{{ $discParts[1] }}</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.06);">
                    <span class="text-[10px] text-amber-700/80 dark:text-amber-400/80">Senior / PWD / Promo</span>
                    <x-filament::badge color="warning" size="xs">Deductions</x-filament::badge>
                </div>
            </div>

            <!-- 4. Cash in Drawer -->
            <div class="sales-kpi-card kpi-accent-cash">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Cash in Drawer</span>
                        <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background-color: rgba(16, 185, 129, 0.15); color: #059669;">
                            <x-filament::icon icon="heroicon-m-banknotes" style="width: 14px; height: 14px;" />
                        </span>
                    </div>
                    <div style="display: flex; align-items: baseline; margin-top: 8px;">
                        <span style="font-size: 13px; font-weight: 700; color: #10b981; margin-right: 2px;">₱</span>
                        <span class="text-xl font-black tracking-tight text-emerald-800 dark:text-emerald-300" style="line-height: 1.1;">{{ $cashParts[0] }}</span>
                        <span style="font-size: 13px; font-weight: 600; color: #10b981;">.{{ $cashParts[1] }}</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.06);">
                    <span class="text-[10px] text-emerald-700/80 dark:text-emerald-400/80">Physical Cash</span>
                    <x-filament::badge color="success" size="xs">In Register</x-filament::badge>
                </div>
            </div>

            <!-- 5. Completed Orders -->
            <div class="sales-kpi-card kpi-accent-orders">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Paid Orders</span>
                        <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background-color: rgba(59, 130, 246, 0.15); color: #2563eb;">
                            <x-filament::icon icon="heroicon-m-shopping-bag" style="width: 14px; height: 14px;" />
                        </span>
                    </div>
                    <div style="display: flex; align-items: baseline; margin-top: 8px;">
                        <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white" style="line-height: 1.1;">{{ number_format($summary['orders_count']) }}</span>
                        <span style="font-size: 12px; font-weight: 600; color: #9ca3af; margin-left: 4px;">orders</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.06);">
                    <span class="text-[10px] text-gray-400">Transactions Handled</span>
                    <x-filament::badge color="info" size="xs">Completed</x-filament::badge>
                </div>
            </div>

            <!-- 6. Average Ticket (AOV) -->
            <div class="sales-kpi-card kpi-accent-aov">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Ticket (AOV)</span>
                        <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background-color: rgba(139, 92, 246, 0.15); color: #7c3aed;">
                            <x-filament::icon icon="heroicon-m-arrow-trending-up" style="width: 14px; height: 14px;" />
                        </span>
                    </div>
                    <div style="display: flex; align-items: baseline; margin-top: 8px;">
                        <span style="font-size: 13px; font-weight: 700; color: #9ca3af; margin-right: 2px;">₱</span>
                        <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white" style="line-height: 1.1;">{{ $aovParts[0] }}</span>
                        <span style="font-size: 13px; font-weight: 600; color: #9ca3af;">.{{ $aovParts[1] }}</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.06);">
                    <span class="text-[10px] text-gray-400">Spend per Customer</span>
                    <x-filament::badge color="gray" size="xs">Basket Size</x-filament::badge>
                </div>
            </div>
        </div>

        <!-- 3. Responsive 2-Column Dashboard Workspace (Left: Operational Velocity, Right: Audit & Tax) -->
        <div class="sales-cockpit-grid">

            <!-- LEFT COLUMN (~58% width): Revenue Trend, Top Products, Cashier Performance -->
            <div style="display: flex; flex-direction: column; gap: 24px; width: 100%;">

                <!-- Daily Net Revenue Trend Chart -->
                <div style="width: 100%;">
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
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100 text-gray-500 dark:border-white/10 dark:text-gray-400">
                                        <th class="pb-2 font-semibold">Rank</th>
                                        <th class="pb-2 font-semibold">Menu Item</th>
                                        <th class="pb-2 text-center font-semibold">Qty Sold</th>
                                        <th class="pb-2 text-right font-semibold">Gross Revenue</th>
                                        <th class="pb-2 text-right font-semibold">Sales Share</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                    @foreach ($summary['top_selling_items'] as $index => $item)
                                        <tr>
                                            <td class="py-2.5 font-semibold text-gray-500 dark:text-gray-400">
                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold {{ $index === 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' }}">
                                                    #{{ $index + 1 }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 font-bold text-gray-900 dark:text-white">
                                                {{ $item['name'] }}
                                            </td>
                                            <td class="py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">
                                                {{ $item['quantity'] }}
                                            </td>
                                            <td class="py-2.5 text-right font-bold text-gray-900 dark:text-white">
                                                ₱{{ number_format($item['revenue'], 2) }}
                                            </td>
                                            <td class="py-2.5 text-right">
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
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100 text-gray-500 dark:border-white/10 dark:text-gray-400">
                                        <th class="pb-2 font-semibold">Tender Method</th>
                                        <th class="pb-2 text-center font-semibold">Transactions</th>
                                        <th class="pb-2 text-right font-semibold">Total Collected</th>
                                        <th class="pb-2 text-right font-semibold">Share (%)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                    @foreach ($summary['payments_breakdown'] as $pay)
                                        <tr>
                                            <td class="py-2.5 font-medium text-gray-900 dark:text-white">
                                                <div class="flex items-center gap-2">
                                                    <span class="h-2 w-2 rounded-full {{ $pay['method'] === 'cash' ? 'bg-emerald-500' : 'bg-primary-500' }}"></span>
                                                    {{ $pay['label'] }}
                                                </div>
                                            </td>
                                            <td class="py-2.5 text-center text-gray-600 dark:text-gray-400">
                                                {{ $pay['count'] }}
                                            </td>
                                            <td class="py-2.5 text-right font-bold text-gray-900 dark:text-white">
                                                ₱{{ number_format($pay['total'], 2) }}
                                            </td>
                                            <td class="py-2.5 text-right">
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
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100 text-gray-500 dark:border-white/10 dark:text-gray-400">
                                        <th class="pb-2.5 font-semibold">Staff Member</th>
                                        <th class="pb-2.5 font-semibold">Emp ID</th>
                                        <th class="pb-2.5 text-center font-semibold">Txns</th>
                                        <th class="pb-2.5 text-right font-semibold">Discounts</th>
                                        <th class="pb-2.5 text-right font-semibold">Net Collected</th>
                                        <th class="pb-2.5 text-right font-semibold">Avg Ticket</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                    @foreach ($summary['cashier_performance'] as $staff)
                                        <tr>
                                            <td class="py-2.5 font-bold text-gray-900 dark:text-white">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-5 w-5 rounded-full bg-gray-100 flex items-center justify-center font-bold text-[9px] text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                                        {{ substr($staff['name'], 0, 1) }}
                                                    </div>
                                                    {{ $staff['name'] }}
                                                </div>
                                            </td>
                                            <td class="py-2.5 text-gray-500 dark:text-gray-400">
                                                <span class="font-mono text-[10px]">{{ $staff['emp_id'] }}</span>
                                            </td>
                                            <td class="py-2.5 text-center font-semibold text-gray-900 dark:text-white">
                                                {{ $staff['orders_count'] }}
                                            </td>
                                            <td class="py-2.5 text-right text-amber-600 dark:text-amber-400 font-medium">
                                                ₱{{ number_format($staff['discounts_given'], 2) }}
                                            </td>
                                            <td class="py-2.5 text-right font-black text-gray-900 dark:text-white">
                                                ₱{{ number_format($staff['net_revenue'], 2) }}
                                            </td>
                                            <td class="py-2.5 text-right text-gray-600 dark:text-gray-400">
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
            <div style="display: flex; flex-direction: column; gap: 24px; width: 100%;">

                <!-- Doughnut Chart: Payment Tender Breakdown -->
                <div style="width: 100%;">
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
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600 dark:text-gray-400">Total Cash Tendered (Handed):</span>
                            <span class="font-medium text-gray-900 dark:text-white font-mono">
                                ₱{{ number_format($summary['cash_reconciliation']['total_cash_tendered'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600 dark:text-gray-400">Total Change Given to Customers:</span>
                            <span class="font-medium text-amber-600 dark:text-amber-400 font-mono">
                                - ₱{{ number_format($summary['cash_reconciliation']['total_change_given'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2.5 bg-emerald-50/50 -mx-3 px-3 rounded-md dark:bg-emerald-950/20">
                            <span class="font-bold text-emerald-800 dark:text-emerald-300">Net Physical Cash in Drawer:</span>
                            <span class="font-black text-emerald-900 dark:text-emerald-200 font-mono text-sm">
                                ₱{{ number_format($summary['cash_reconciliation']['net_cash_in_drawer'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600 dark:text-gray-400">Total Digital & Card Payments:</span>
                            <span class="font-bold text-blue-600 dark:text-blue-400 font-mono">
                                ₱{{ number_format($summary['cash_reconciliation']['total_digital'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600 dark:text-gray-400">Refunds / Void Losses:</span>
                            <span class="font-medium font-mono {{ ($summary['refunds_summary']['refunded_amount'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500' }}">
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
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600 dark:text-gray-400">VATable Sales (12% Base):</span>
                            <span class="font-bold text-gray-900 dark:text-white font-mono">
                                ₱{{ number_format($summary['tax_summary']['vatable_sales'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600 dark:text-gray-400">VAT Output Amount (12%):</span>
                            <span class="font-bold text-primary-600 dark:text-primary-400 font-mono">
                                ₱{{ number_format($summary['tax_summary']['vat_amount'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600 dark:text-gray-400">VAT-Exempt Sales (Senior/PWD):</span>
                            <span class="font-bold text-gray-900 dark:text-white font-mono">
                                ₱{{ number_format($summary['tax_summary']['vat_exempt_sales'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600 dark:text-gray-400">Zero-Rated Sales:</span>
                            <span class="font-bold text-gray-900 dark:text-white font-mono">
                                ₱{{ number_format($summary['tax_summary']['zero_rated_sales'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2.5 bg-gray-50/50 -mx-3 px-3 rounded-b-lg dark:bg-white/5">
                            <span class="font-bold text-gray-700 dark:text-gray-300">Total Taxable Sales Base:</span>
                            <span class="font-black text-gray-900 dark:text-white font-mono text-sm">
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

                    <div style="display: flex; flex-direction: column; gap: 14px; padding-top: 4px;">
                        <!-- Dine In -->
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-primary-600"></span> Dine-In
                                </span>
                                <span class="font-bold text-gray-900 dark:text-white">
                                    {{ $dineInCount }} orders
                                    <span class="text-gray-400 font-normal">({{ $totalTypesCount > 0 ? round(($dineInCount / $totalTypesCount) * 100, 1) : 0 }}%)</span>
                                </span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                                <div class="h-full bg-primary-600 rounded-full" style="width: {{ $totalTypesCount > 0 ? ($dineInCount / $totalTypesCount) * 100 : 0 }}%"></div>
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
