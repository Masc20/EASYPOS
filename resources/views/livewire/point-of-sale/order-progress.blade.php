<div x-data="{
    open: @entangle('isOpen'),
    activeFilter: 'all',
    searchQuery: '',
    matches(status, searchableText) {
        const matchesStatus = this.activeFilter === 'all' || status === this.activeFilter;
        if (!matchesStatus) {
            return false;
        }
        if (!this.searchQuery) {
            return true;
        }
        const q = this.searchQuery.toLowerCase().trim();
        const cleanTable = q.replace(/^table\s+/i, '');
        return searchableText.includes(q) || (cleanTable.length > 0 && searchableText.includes(cleanTable));
    }
}" 
x-show="open" 
x-on:open-order-progress.window="open = true; $wire.open();"
x-on:close-order-progress.window="open = false; $wire.close();"
x-on:keydown.escape.window="open = false; $wire.close();"
class="relative z-50">

    <!-- Backdrop -->
    <div x-show="open"
         x-transition:enter="ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-on:click="open = false; $wire.close();"
         class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"></div>

    <!-- Slide-over Drawer Panel -->
    <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div x-show="open"
             x-transition:enter="transform transition ease-in-out duration-200"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in-out duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="w-screen max-w-xl bg-brand-bg border-l border-brand-border flex flex-col shadow-2xl">

            <!-- Drawer Header -->
            <div class="px-5 py-4 border-b border-brand-border bg-brand-card/90 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-brand-text flex items-center gap-2">
                            <svg class="h-5 w-5 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            Kitchen Order Progress
                        </h2>
                        <span class="inline-flex items-center gap-1 rounded-full bg-brand-success/10 border border-brand-success/30 px-2 py-0.5 text-[10px] font-semibold text-brand-success">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-success animate-pulse"></span>
                            Live Feed
                        </span>
                    </div>
                    <p class="text-xs text-brand-text-muted mt-0.5">
                        {{ $branch?->name ?? 'Branch Kitchen' }} • Read-only floor tracking
                    </p>
                </div>
                <div class="flex items-center gap-1.5">
                    <!-- Manual Refresh Button -->
                    <button type="button" wire:click="refreshTickets"
                        class="h-8 w-8 rounded-md border border-brand-border bg-brand-card hover:bg-brand-muted text-brand-text-muted hover:text-brand-text transition flex items-center justify-center cursor-pointer focus:outline-none focus-visible:ring-1 focus-visible:ring-brand-primary"
                        title="Refresh kitchen orders">
                        <svg wire:loading.class="animate-spin text-brand-primary" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                    <!-- Close Button -->
                    <button type="button" x-on:click="open = false; $wire.close();"
                        class="h-8 w-8 rounded-md border border-brand-border bg-brand-card hover:bg-brand-muted text-brand-text-muted hover:text-brand-text transition flex items-center justify-center cursor-pointer focus:outline-none focus-visible:ring-1 focus-visible:ring-brand-primary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Search Bar & Filters -->
            <div class="p-4 border-b border-brand-border bg-brand-card/60 space-y-3">
                <!-- Search Input (Instant 0ms Client-Side Alpine Filtering) -->
                <div class="relative">
                    <input type="text"
                        x-model="searchQuery"
                        placeholder="Search by ticket #, order #, table #, customer..."
                        class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 pl-9 text-xs text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary shadow-2xs font-medium">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-brand-text-muted">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <button type="button" x-show="searchQuery.length > 0" x-on:click="searchQuery = ''"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-brand-text-muted hover:text-brand-text cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Status Filter Chips (Instant 0ms Client-Side Alpine Switch) -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 scrollbar-thin select-none">
                    <!-- All -->
                    <button type="button" 
                        x-on:click="activeFilter = 'all'"
                        :class="activeFilter === 'all' 
                            ? 'bg-brand-primary text-white border-brand-primary shadow-xs' 
                            : 'bg-brand-bg text-brand-text-muted border-brand-border hover:bg-brand-muted hover:text-brand-text'"
                        class="shrink-0 px-2.5 py-1 text-[11px] font-semibold rounded-md border transition cursor-pointer select-none inline-flex items-center gap-1.5 focus:outline-none focus-visible:ring-1 focus-visible:ring-brand-primary active:scale-95">
                        <span>All</span>
                        <span :class="activeFilter === 'all' ? 'bg-white/20 text-white' : 'bg-brand-muted text-brand-text-muted'"
                            class="rounded-full px-1.5 py-0.2 text-[9px] font-bold">
                            {{ $counts['all'] }}
                        </span>
                    </button>

                    <!-- New -->
                    <button type="button" 
                        x-on:click="activeFilter = 'new'"
                        :class="activeFilter === 'new' 
                            ? 'bg-sky-600 text-white border-sky-600 shadow-xs' 
                            : 'bg-brand-bg text-sky-700 dark:text-sky-300 border-brand-border hover:bg-sky-500/10'"
                        class="shrink-0 px-2.5 py-1 text-[11px] font-semibold rounded-md border transition cursor-pointer select-none inline-flex items-center gap-1.5 focus:outline-none focus-visible:ring-1 focus-visible:ring-sky-500 active:scale-95">
                        <span>New</span>
                        <span :class="activeFilter === 'new' ? 'bg-white/20 text-white' : 'bg-sky-500/15 text-sky-700 dark:text-sky-300'"
                            class="rounded-full px-1.5 py-0.2 text-[9px] font-bold">
                            {{ $counts['new'] }}
                        </span>
                    </button>

                    <!-- In Prep -->
                    <button type="button" 
                        x-on:click="activeFilter = 'in_prep'"
                        :class="activeFilter === 'in_prep' 
                            ? 'bg-amber-600 text-white border-amber-600 shadow-xs' 
                            : 'bg-brand-bg text-amber-700 dark:text-amber-300 border-brand-border hover:bg-amber-500/10'"
                        class="shrink-0 px-2.5 py-1 text-[11px] font-semibold rounded-md border transition cursor-pointer select-none inline-flex items-center gap-1.5 focus:outline-none focus-visible:ring-1 focus-visible:ring-amber-500 active:scale-95">
                        <span>In Prep</span>
                        <span :class="activeFilter === 'in_prep' ? 'bg-white/20 text-white' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'"
                            class="rounded-full px-1.5 py-0.2 text-[9px] font-bold">
                            {{ $counts['in_prep'] }}
                        </span>
                    </button>

                    <!-- Ready -->
                    <button type="button" 
                        x-on:click="activeFilter = 'ready'"
                        :class="activeFilter === 'ready' 
                            ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' 
                            : 'bg-brand-bg text-emerald-700 dark:text-emerald-300 border-brand-border hover:bg-emerald-500/10'"
                        class="shrink-0 px-2.5 py-1 text-[11px] font-semibold rounded-md border transition cursor-pointer select-none inline-flex items-center gap-1.5 focus:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 active:scale-95">
                        <span>Ready</span>
                        <span :class="activeFilter === 'ready' ? 'bg-white/20 text-white' : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'"
                            class="rounded-full px-1.5 py-0.2 text-[9px] font-bold">
                            {{ $counts['ready'] }}
                        </span>
                    </button>

                    <!-- Served -->
                    <button type="button" 
                        x-on:click="activeFilter = 'served'"
                        :class="activeFilter === 'served' 
                            ? 'bg-slate-700 text-white border-slate-700 shadow-xs' 
                            : 'bg-brand-bg text-brand-text-muted border-brand-border hover:bg-brand-muted hover:text-brand-text'"
                        class="shrink-0 px-2.5 py-1 text-[11px] font-semibold rounded-md border transition cursor-pointer select-none inline-flex items-center gap-1.5 focus:outline-none focus-visible:ring-1 focus-visible:ring-slate-500 active:scale-95">
                        <span>Served</span>
                        <span :class="activeFilter === 'served' ? 'bg-white/20 text-white' : 'bg-brand-muted text-brand-text-muted'"
                            class="rounded-full px-1.5 py-0.2 text-[9px] font-bold">
                            {{ $counts['served'] }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Ticket List Body -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-thin">
                @forelse ($tickets as $ticket)
                    @php
                        $statusBadgeClass = match ($ticket->status) {
                            'new' => 'bg-sky-500/15 border-sky-500/30 text-sky-700 dark:text-sky-300 font-semibold',
                            'in_prep' => 'bg-amber-500/15 border-amber-500/30 text-amber-700 dark:text-amber-300 font-semibold',
                            'ready' => 'bg-emerald-500/15 border-emerald-500/40 text-emerald-700 dark:text-emerald-300 font-bold',
                            'served' => 'bg-slate-500/15 border-slate-500/25 text-slate-600 dark:text-slate-400 font-medium',
                            default => 'bg-brand-muted border-brand-border text-brand-text-muted',
                        };

                        $statusLabel = match ($ticket->status) {
                            'new' => 'New / Queued',
                            'in_prep' => 'Cooking (In Prep)',
                            'ready' => 'Ready for Pickup',
                            'served' => 'Served',
                            'cancelled' => 'Cancelled',
                            default => ucfirst($ticket->status),
                        };

                        $isReady = $ticket->status === 'ready';

                        $searchable = strtolower(
                            $ticket->ticket_number . ' ' .
                            ($ticket->order?->order_number ?? '') . ' ' .
                            ($ticket->order?->table_number ? 'table ' . $ticket->order->table_number . ' ' . $ticket->order->table_number : '') . ' ' .
                            ($ticket->order?->customer_name ?? '') . ' ' .
                            $ticket->items->map(fn($i) => $i->orderItem?->meal_name ?? '')->join(' ')
                        );
                    @endphp

                    <div x-show="matches('{{ $ticket->status }}', '{{ addslashes($searchable) }}')"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-98"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="rounded-lg border p-3.5 transition select-none
                             {{ $isReady
                                 ? 'border-emerald-500/50 bg-emerald-500/5 shadow-xs ring-1 ring-emerald-500/20'
                                 : 'border-brand-border bg-brand-card hover:border-brand-primary/30 shadow-2xs' }}">
                        
                        <!-- Ticket Header -->
                        <div class="flex items-start justify-between gap-2 pb-2.5 border-b border-brand-border/60">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm font-bold text-brand-text">
                                        {{ $ticket->ticket_number }}
                                    </span>
                                    @if ($ticket->priority === 'rush')
                                        <span class="inline-flex items-center gap-1 rounded-sm bg-brand-error/15 border border-brand-error/30 px-1.5 py-0.2 text-[10px] font-bold text-brand-error uppercase tracking-wider animate-pulse">
                                            Rush
                                        </span>
                                    @endif
                                    <span class="text-xs text-brand-text-muted">
                                        Order #{{ $ticket->order?->order_number ?? 'N/A' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-brand-text-muted mt-0.5">
                                    <span class="font-semibold text-brand-text">
                                        {{ $ticket->order?->table_number ? 'Table ' . $ticket->order->table_number : ucfirst(str_replace('_', ' ', $ticket->order?->order_type ?? 'dine_in')) }}
                                    </span>
                                    @if ($ticket->order?->customer_name)
                                        <span>•</span>
                                        <span>{{ $ticket->order->customer_name }}</span>
                                    @endif
                                    <span>•</span>
                                    <span class="text-[11px] {{ $ticket->urgency_level === 'urgent' ? 'text-brand-error font-bold' : ($ticket->urgency_level === 'warning' ? 'text-amber-600 font-semibold' : 'text-brand-text-muted') }}">
                                        {{ $ticket->elapsed_minutes }}m ago
                                    </span>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="shrink-0 text-right">
                                <span class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold {{ $statusBadgeClass }}">
                                    @if ($isReady)
                                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-ping"></span>
                                    @endif
                                    {{ $statusLabel }}
                                </span>
                                @if ($ticket->cook)
                                    <p class="text-[10px] text-brand-text-muted mt-1">
                                        Cook: {{ $ticket->cook->name }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <!-- Ticket Items Breakdown -->
                        <div class="pt-2.5 space-y-1.5">
                            @foreach ($ticket->items as $item)
                                @php
                                    $itemDone = $item->status === 'done';
                                    $orderItem = $item->orderItem;
                                @endphp
                                <div class="flex items-center justify-between text-xs py-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold {{ $itemDone ? 'text-brand-text-muted line-through' : 'text-brand-text' }}">
                                            {{ $orderItem?->quantity ?? 1 }}x
                                        </span>
                                        <span class="{{ $itemDone ? 'text-brand-text-muted line-through' : 'font-medium text-brand-text' }}">
                                            {{ $orderItem?->meal_name ?? 'Dish item' }}
                                        </span>
                                        @if ($orderItem?->special_instructions)
                                            <span class="text-[10px] italic text-amber-600 dark:text-amber-400">
                                                ({{ $orderItem->special_instructions }})
                                            </span>
                                        @endif
                                    </div>
                                    <div>
                                        @if ($itemDone)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                Done
                                            </span>
                                        @else
                                            <span class="text-[10px] text-brand-text-muted font-mono">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Read-only Footer Tag -->
                        <div class="mt-2.5 pt-2 border-t border-brand-border/40 flex items-center justify-between text-[10px] text-brand-text-muted">
                            <span class="inline-flex items-center gap-1 font-mono">
                                <svg class="h-3 w-3 text-brand-text-muted/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Read-only • Floor Terminal
                            </span>
                            @if ($ticket->ready_at)
                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">
                                    Ready since {{ $ticket->ready_at->format('g:i A') }}
                                </span>
                            @elseif ($ticket->started_at)
                                <span>
                                    Started {{ $ticket->started_at->format('g:i A') }}
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-16 text-center">
                        <svg class="mx-auto h-12 w-12 text-brand-text-muted/40 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <p class="text-sm font-semibold text-brand-text">No orders matching filter</p>
                        <p class="text-xs text-brand-text-muted mt-0.5">Tickets sent to the kitchen after checkout will appear here in real time.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
