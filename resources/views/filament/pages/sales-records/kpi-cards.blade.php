<!-- 2. High-Impact Executive KPI Cards Grid -->
@php
    $grossParts = explode('.', number_format($summary['gross_sales'] ?? 0, 2));
    $netParts = explode('.', number_format($summary['net_sales'] ?? 0, 2));
    $discParts = explode('.', number_format($summary['total_discounts'] ?? 0, 2));
    $cashParts = explode('.', number_format($summary['cash_reconciliation']['net_cash_in_drawer'] ?? 0, 2));
    $aovParts = explode('.', number_format($summary['average_order_value'] ?? 0, 2));
@endphp

<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3.5 w-full">
    <!-- 1. Gross Sales -->
    <div
        class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-gray-400 shadow-xs relative overflow-hidden box-border">
        <div class="flex items-center justify-between gap-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gross Sales</span>
            <span
                class="flex items-center justify-center w-6 h-6 rounded-md bg-gray-100 dark:bg-white/10 text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-m-calculator" class="w-3.5 h-3.5" />
            </span>
        </div>
        <div class="flex items-baseline mt-2">
            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 mr-0.5">₱</span>
            <span
                class="text-xl font-black tracking-tight text-gray-900 dark:text-white leading-tight">{{ $grossParts[0] }}</span>
            <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">.{{ $grossParts[1] }}</span>
        </div>
    </div>

    <!-- 2. Net Revenue -->
    <div
        class="flex flex-col justify-between rounded-xl p-4 bg-[#fdf5f5] dark:bg-[#960D10]/15 border border-[#960D10]/25 dark:border-[#960D10]/35 border-l-4 border-l-[#960D10] shadow-xs relative overflow-hidden box-border">
        <div class="flex items-center justify-between gap-1">
            <span
                class="text-[11px] font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">Net Revenue</span>
            <span
                class="flex items-center justify-center w-6 h-6 rounded-md bg-[#960D10]/15 text-[#960D10] dark:text-[#DE9A9D]">
                <x-filament::icon icon="heroicon-m-currency-dollar" class="w-3.5 h-3.5" />
            </span>
        </div>
        <div class="flex items-baseline mt-2">
            <span class="text-xs font-bold text-[#DE9A9D] mr-0.5">₱</span>
            <span
                class="text-xl font-black tracking-tight text-primary-900 dark:text-primary-100 leading-tight">{{ $netParts[0] }}</span>
            <span class="text-xs font-semibold text-[#DE9A9D]">.{{ $netParts[1] }}</span>
        </div>
    </div>

    <!-- 3. Discounts Granted -->
    <div
        class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-amber-500 shadow-xs relative overflow-hidden box-border">
        <div class="flex items-center justify-between gap-1">
            <span
                class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Discounts</span>
            <span
                class="flex items-center justify-center w-6 h-6 rounded-md bg-amber-500/15 text-amber-600 dark:text-amber-400">
                <x-filament::icon icon="heroicon-m-tag" class="w-3.5 h-3.5" />
            </span>
        </div>
        <div class="flex items-baseline mt-2">
            <span class="text-xs font-bold text-amber-500 mr-0.5">-₱</span>
            <span
                class="text-xl font-black tracking-tight text-amber-800 dark:text-amber-300 leading-tight">{{ $discParts[0] }}</span>
            <span class="text-xs font-semibold text-amber-500">.{{ $discParts[1] }}</span>
        </div>
    </div>

    <!-- 4. Cash in Drawer -->
    <div
        class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-emerald-500 shadow-xs relative overflow-hidden box-border">
        <div class="flex items-center justify-between gap-1">
            <span
                class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Cash in Drawer</span>
            <span
                class="flex items-center justify-center w-6 h-6 rounded-md bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                <x-filament::icon icon="heroicon-m-banknotes" class="w-3.5 h-3.5" />
            </span>
        </div>
        <div class="flex items-baseline mt-2">
            <span class="text-xs font-bold text-emerald-500 mr-0.5">₱</span>
            <span
                class="text-xl font-black tracking-tight text-emerald-800 dark:text-emerald-300 leading-tight">{{ $cashParts[0] }}</span>
            <span class="text-xs font-semibold text-emerald-500">.{{ $cashParts[1] }}</span>
        </div>
    </div>

    <!-- 5. Completed Orders -->
    <div
        class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-blue-500 shadow-xs relative overflow-hidden box-border">
        <div class="flex items-center justify-between gap-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Paid Orders</span>
            <span
                class="flex items-center justify-center w-6 h-6 rounded-md bg-blue-500/15 text-blue-600 dark:text-blue-400">
                <x-filament::icon icon="heroicon-m-shopping-bag" class="w-3.5 h-3.5" />
            </span>
        </div>
        <div class="flex items-baseline mt-2">
            <span
                class="text-xl font-black tracking-tight text-gray-900 dark:text-white leading-tight">{{ number_format($summary['orders_count'] ?? 0) }}</span>
            <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 ml-1">orders</span>
        </div>
    </div>

    <!-- 6. Average Ticket (AOV) -->
    <div
        class="flex flex-col justify-between rounded-xl p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 border-l-4 border-l-purple-500 shadow-xs relative overflow-hidden box-border">
        <div class="flex items-center justify-between gap-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Ticket (AOV)</span>
            <span
                class="flex items-center justify-center w-6 h-6 rounded-md bg-purple-500/15 text-purple-600 dark:text-purple-400">
                <x-filament::icon icon="heroicon-m-arrow-trending-up" class="w-3.5 h-3.5" />
            </span>
        </div>
        <div class="flex items-baseline mt-2">
            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 mr-0.5">₱</span>
            <span
                class="text-xl font-black tracking-tight text-gray-900 dark:text-white leading-tight">{{ $aovParts[0] }}</span>
            <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">.{{ $aovParts[1] }}</span>
        </div>
    </div>
</div>
