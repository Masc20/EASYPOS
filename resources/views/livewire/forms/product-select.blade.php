<div class="relative w-full" x-data="{ open: false }" x-on:click.outside="open = false">
    @if ($selectedMeal)
        <div class="flex items-center justify-between rounded-md border border-brand-border bg-brand-card p-2.5 shadow-2xs">
            <div class="flex items-center gap-2.5 overflow-hidden">
                <div class="h-8 w-8 shrink-0 rounded-sm bg-brand-primary/10 flex items-center justify-center text-brand-primary font-bold text-xs uppercase border border-brand-primary/20">
                    {{ substr($selectedMeal->name, 0, 2) }}
                </div>
                <div class="truncate">
                    <span class="block truncate text-xs font-bold text-brand-text">{{ $selectedMeal->name }}</span>
                    <div class="flex items-center gap-1.5 text-[10px] text-brand-text-muted">
                        @if ($selectedMeal->menu_code)
                            <span class="font-mono bg-brand-bg px-1 rounded-xs">{{ $selectedMeal->menu_code }}</span>
                        @endif
                        <span class="font-semibold text-brand-primary">₱{{ number_format($selectedMeal->price, 2) }}</span>
                    </div>
                </div>
            </div>
            @if (!$disabled)
                <button type="button"
                    wire:click="clearSelection"
                    class="rounded-sm p-1 text-brand-text-muted hover:bg-brand-bg hover:text-brand-error transition cursor-pointer"
                    title="Clear selection">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>
    @else
        <div class="relative">
            <input type="text"
                wire:model.live.debounce.250ms="search"
                x-on:focus="open = true"
                @disabled($disabled)
                placeholder="{{ $placeholder }}"
                class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 pl-9 text-xs text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 transition shadow-2xs">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-brand-text-muted">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            @if ($search)
                <button type="button"
                    wire:click="$set('search', '')"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-brand-text-muted hover:text-brand-text cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

        @if (strlen(trim($search)) >= 1)
            <div x-show="open"
                class="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-brand-border bg-brand-card p-1 shadow-lg">
                @forelse ($results as $meal)
                    <button type="button"
                        wire:click="selectMeal({{ $meal->id }})"
                        x-on:click="open = false"
                        class="w-full flex items-center justify-between rounded-sm px-2.5 py-2 text-left hover:bg-brand-bg transition cursor-pointer">
                        <div class="truncate">
                            <span class="block truncate text-xs font-semibold text-brand-text">{{ $meal->name }}</span>
                            <span class="text-[10px] text-brand-text-muted">
                                {{ $meal->category->name ?? 'Uncategorized' }}
                                @if ($meal->menu_code)
                                    • <span class="font-mono">{{ $meal->menu_code }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="text-right pl-2">
                            <span class="text-xs font-bold text-brand-primary">₱{{ number_format($meal->price, 2) }}</span>
                            @if ($meal->track_stock)
                                <span class="block text-[9px] font-mono {{ $meal->stock_quantity <= 5 ? 'text-brand-error' : 'text-zinc-500' }}">
                                    {{ $meal->stock_quantity }} in stock
                                </span>
                            @endif
                        </div>
                    </button>
                @empty
                    <div class="p-3 text-center text-xs text-brand-text-muted">
                        No available dishes found for "<span class="font-semibold">{{ $search }}</span>".
                    </div>
                @endforelse
            </div>
        @endif
    @endif
</div>
