<x-filament-panels::page>
    <div class="flex flex-col gap-6 w-full relative">

        {{-- 1. Unified Filter & Controls Bar --}}
        @include('filament.pages.sales-records.filters')

        {{-- 2. High-Impact Executive KPI Cards Grid --}}
        @include('filament.pages.sales-records.kpi-cards')

        {{-- 3. Responsive 2-Column Audit Workspace --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">
            {{-- LEFT COLUMN (col-span-6): Cash Drawer, Tender Reconciliation, Cashier Performance --}}
            <div class="flex flex-col gap-6 w-full lg:col-span-6">
                @include('filament.pages.sales-records.cash-reconciliation')
            </div>

            {{-- RIGHT COLUMN (col-span-6): Tax Compliance, Doughnut Chart, Dining Mode --}}
            <div class="flex flex-col gap-6 w-full lg:col-span-6">
                @include('filament.pages.sales-records.tax-compliance')
            </div>
        </div>

        {{-- 4. Periodic Sales Reports & Tax Audit (SCRUM-171, 172, 173) --}}
        @include('filament.pages.sales-records.periodic-reports')

    </div>
</x-filament-panels::page>
