<!-- 1. Unified Filter & Controls Bar -->
<x-filament::section icon="heroicon-m-funnel" compact>
    <x-slot name="heading">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Sales Period and Station Filters</h2>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-arrow-path"
                    wire:click="resetFilters" tooltip="Reset filters to today">
                    Reset
                </x-filament::button>

                <x-filament::button color="success" size="sm" icon="heroicon-m-arrow-down-tray"
                    wire:click="exportCsv">
                    Export CSV Summary
                </x-filament::button>
            </div>
        </div>
    </x-slot>

    <div class="flex flex-col gap-3 pt-1">
        <!-- Preset Buttons -->
        <div class="flex flex-wrap items-center gap-1.5">
            <span
                class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mr-2">Period:</span>
            <x-filament::button size="xs" :color="$selectedPreset === 'today' ? 'primary' : 'gray'" wire:click="setPreset('today')">
                Today
            </x-filament::button>
            <x-filament::button size="xs" :color="$selectedPreset === 'yesterday' ? 'primary' : 'gray'" wire:click="setPreset('yesterday')">
                Yesterday
            </x-filament::button>
            <x-filament::button size="xs" :color="$selectedPreset === 'this_week' ? 'primary' : 'gray'" wire:click="setPreset('this_week')">
                This Week
            </x-filament::button>
            <x-filament::button size="xs" :color="$selectedPreset === 'this_month' ? 'primary' : 'gray'" wire:click="setPreset('this_month')">
                This Month
            </x-filament::button>
            <x-filament::button size="xs" :color="$selectedPreset === 'this_year' ? 'primary' : 'gray'" wire:click="setPreset('this_year')">
                This Year
            </x-filament::button>
            <x-filament::button size="xs" :color="$selectedPreset === 'all_time' ? 'primary' : 'gray'" wire:click="setPreset('all_time')">
                All Time
            </x-filament::button>
        </div>

        <!-- Custom Pickers Grid -->
        <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 pt-2.5 border-t border-gray-100 dark:border-white/10">
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

            @if (!($isBranchScoped ?? false))
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
            @else
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Assigned Branch</label>
                    <div
                        class="px-3 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-white/10 text-gray-900 dark:text-white">
                        {{ auth()->user()->branch?->name ?? 'Branch #' . auth()->user()->branch_id }}
                    </div>
                </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cashier Station</label>
                <x-filament::input.wrapper prefixIcon="heroicon-m-user">
                    <x-filament::input.select wire:model.live="cashierId">
                        <option value="">All Staff Members</option>
                        @foreach ($cashiers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}
                                ({{ $c->emp_id ?: 'Admin' }})
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </div>
</x-filament::section>
