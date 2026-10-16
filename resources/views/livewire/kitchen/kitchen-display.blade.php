<div x-data="{
    diningFilter: '{{ $diningFilter }}',
    priorityFilter: '{{ $priorityFilter }}',
    showRecallDrawer: false,
    matchesFilter(orderType, priority) {
        const matchDining = this.diningFilter === 'all' || this.diningFilter === orderType;
        const matchPriority = this.priorityFilter === 'all' || this.priorityFilter === priority;
        return matchDining && matchPriority;
    },
    playChime() {
        try {
            const ctx = new(window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.setValueAtTime(1174.66, ctx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
            osc.start();
            osc.stop(ctx.currentTime + 0.6);
        } catch (e) {}
    }
}" x-on:play-kitchen-chime.window="playChime()" wire:poll.visible.5s="pollUpdates" class="space-y-6">

    <!-- Top Station Header & Filters -->
    <div
        class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-brand-card p-4 rounded-md border border-brand-border shadow-2xs">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-xl font-black uppercase tracking-tight text-brand-text">Kitchen Display System (KDS)</h1>
                <span
                    class="inline-flex items-center gap-1.5 rounded-sm bg-brand-success/15 border border-brand-success/30 px-2 py-0.5 text-xs font-bold text-brand-success">
                    <span class="h-2 w-2 rounded-full bg-brand-success animate-pulse"></span>
                    Live Queue ({{ $counts['total_active'] }})
                </span>
            </div>
        </div>

        <!-- Filter Controls & Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Dining Type Filter (0ms Instant Client-Side Alpine Switch) -->
            <div class="inline-flex rounded-md border border-brand-border bg-brand-bg p-0.5 shadow-2xs">
                <button type="button" x-on:click="diningFilter = 'all'"
                    :class="diningFilter === 'all' ? 'bg-brand-primary text-white' :
                        'text-brand-text-muted hover:text-brand-text'"
                    class="rounded-sm px-2.5 py-1 text-xs font-semibold transition cursor-pointer">
                    All
                </button>
                <button type="button" x-on:click="diningFilter = 'dine_in'"
                    :class="diningFilter === 'dine_in' ? 'bg-brand-primary text-white' :
                        'text-brand-text-muted hover:text-brand-text'"
                    class="rounded-sm px-2.5 py-1 text-xs font-semibold transition cursor-pointer">
                    Dine In
                </button>
                <button type="button" x-on:click="diningFilter = 'takeout'"
                    :class="diningFilter === 'takeout' ? 'bg-brand-primary text-white' :
                        'text-brand-text-muted hover:text-brand-text'"
                    class="rounded-sm px-2.5 py-1 text-xs font-semibold transition cursor-pointer">
                    Takeout
                </button>
            </div>

            <!-- Priority Filter (0ms Instant Client-Side Alpine Switch) -->
            <button type="button" x-on:click="priorityFilter = (priorityFilter === 'rush' ? 'all' : 'rush')"
                :class="priorityFilter === 'rush' ? 'bg-brand-error text-white border-brand-error shadow-2xs' :
                    'border-brand-border bg-brand-bg text-brand-text-muted hover:text-brand-text'"
                class="rounded-md border px-2.5 py-1 text-xs font-bold transition cursor-pointer inline-flex items-center gap-1">
                <span>Rush Only</span>
            </button>

            <!-- Sound Alert Toggle -->
            <button type="button" wire:click="toggleAudioAlert"
                class="rounded-md border px-2.5 py-1 text-xs font-semibold transition cursor-pointer inline-flex items-center gap-1.5 {{ $audioAlertEnabled ? 'border-brand-border bg-brand-bg text-brand-text' : 'border-brand-border bg-brand-card text-brand-text-muted line-through' }}"
                title="{{ $audioAlertEnabled ? 'Chime sound is active on new orders' : 'Sound alerts muted' }}">
                <svg class="h-3.5 w-3.5 {{ $audioAlertEnabled ? 'text-amber-500' : 'text-brand-text-muted' }}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span>{{ $audioAlertEnabled ? 'Chime On' : 'Muted' }}</span>
            </button>

            <!-- Recall History Button (0ms Instant Slide-Over Toggle) -->
            <button type="button" x-on:click="showRecallDrawer = true"
                class="rounded-md border border-brand-border bg-brand-card hover:bg-brand-bg px-2.5 py-1 text-xs font-semibold text-brand-text transition cursor-pointer inline-flex items-center gap-1 shadow-2xs">
                <svg class="h-3.5 w-3.5 text-brand-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Recall History</span>
            </button>
        </div>
    </div>

    <!-- 3-Column Kanban Queue -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1: New Orders (Unclaimed) -->
        <div class="flex flex-col rounded-md border border-brand-border bg-brand-card shadow-2xs">
            <div class="flex items-center justify-between p-3.5 border-b border-brand-border bg-brand-bg/50">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-brand-secondary"></span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-brand-text">1. New Orders</h2>
                </div>
                <span
                    class="rounded-sm bg-brand-secondary/15 text-brand-secondary px-2 py-0.5 text-xs font-bold font-mono">
                    {{ $counts['new'] }} Tickets
                </span>
            </div>

            <div class="p-3.5 space-y-3.5 overflow-y-auto max-h-[calc(100vh-250px)]">
                @forelse ($newTickets as $ticket)
                    <div wire:key="ticket-{{ $ticket->id }}"
                        x-show="matchesFilter('{{ $ticket->order->order_type }}', '{{ $ticket->priority }}')"
                        class="rounded-md border transition shadow-xs p-3.5 space-y-3 bg-brand-card {{ $ticket->priority === 'rush' ? 'border-brand-error ring-2 ring-brand-error/20' : ($ticket->urgency_level === 'urgent' ? 'border-brand-error' : ($ticket->urgency_level === 'warning' ? 'border-amber-500/70' : 'border-brand-border')) }}">

                        <!-- Ticket Card Header -->
                        <div class="flex items-start justify-between pb-2 border-b border-dashed border-brand-border">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-base font-black font-mono text-brand-text">{{ $ticket->ticket_number }}</span>
                                    @if ($ticket->priority === 'rush')
                                        <span
                                            class="inline-flex items-center gap-1 rounded-sm bg-brand-error px-1.5 py-0.5 text-[10px] font-black text-white uppercase animate-pulse">
                                            RUSH
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-brand-text-muted mt-0.5">
                                    {{ $ticket->order->order_number }}
                                    @if ($ticket->order->table_number)
                                        • <strong class="text-brand-text">Table {{ $ticket->order->table_number }}</strong>
                                    @endif
                                    • <span
                                        class="uppercase font-semibold">{{ $ticket->order->order_type_label }}</span>
                                </div>
                            </div>

                            <!-- Elapsed Time Badge -->
                            <div class="text-right">
                                <span
                                    class="inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-[11px] font-mono font-bold {{ $ticket->urgency_level === 'urgent' ? 'bg-brand-error/15 text-brand-error border border-brand-error/30' : ($ticket->urgency_level === 'warning' ? 'bg-amber-500/15 text-amber-500 border border-amber-500/30' : 'bg-brand-bg text-brand-text-muted border border-brand-border') }}">
                                    ⏱ {{ $ticket->elapsed_minutes }}m ago
                                </span>
                                <div class="text-[10px] text-brand-text-muted mt-0.5 font-mono">
                                    {{ $ticket->created_at->format('h:i A') }}
                                </div>
                            </div>
                        </div>

                        <!-- Dishes Breakdown -->
                        <div class="space-y-2 py-1">
                            @foreach ($ticket->items as $item)
                                <div class="flex items-start justify-between text-xs">
                                    <div class="flex-1 pr-2">
                                        <span
                                            class="font-bold text-brand-text mr-1.5 font-mono text-sm">{{ $item->orderItem->quantity }}x</span>
                                        <span
                                            class="font-semibold text-brand-text">{{ $item->orderItem->meal_name }}</span>

                                        @if ($item->orderItem->special_instructions)
                                            <div
                                                class="mt-1 flex items-center gap-1 rounded-xs bg-amber-500/15 border border-amber-500/30 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400">
                                                <span>⚠️ NOTE:</span>
                                                <span>{{ $item->orderItem->special_instructions }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Ticket Action Controls -->
                        <div class="pt-2 border-t border-brand-border flex items-center gap-2">
                            <button type="button" wire:click="claimTicket({{ $ticket->id }})"
                                wire:loading.attr="disabled"
                                wire:target="claimTicket({{ $ticket->id }})"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-md bg-brand-primary hover:bg-brand-primary-hover disabled:opacity-60 text-white py-2 text-xs font-bold uppercase tracking-wider transition cursor-pointer shadow-2xs">
                                <span wire:loading.remove wire:target="claimTicket({{ $ticket->id }})" class="inline-flex items-center gap-1.5">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Start Cooking</span>
                                </span>
                                <span wire:loading wire:target="claimTicket({{ $ticket->id }})" class="inline-flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>Claiming...</span>
                                </span>
                            </button>

                            <button type="button" wire:click="togglePriority({{ $ticket->id }})"
                                wire:loading.attr="disabled"
                                wire:target="togglePriority({{ $ticket->id }})"
                                title="Toggle Rush Priority"
                                class="rounded-md border border-brand-border hover:bg-brand-bg p-2 text-brand-text-muted hover:text-brand-error transition cursor-pointer">
                                🔥
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-center text-brand-text-muted">
                        <div class="h-10 w-10 rounded-full bg-brand-bg flex items-center justify-center mb-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-brand-text">No New Tickets</p>
                        <p class="text-[11px] text-brand-text-muted mt-0.5">Orders sent from the register will appear
                            here</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 2: In Preparation (Active Cooking) -->
        <div class="flex flex-col rounded-md border border-brand-border bg-brand-card shadow-2xs">
            <div class="flex items-center justify-between p-3.5 border-b border-brand-border bg-brand-bg/50">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-amber-500"></span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-brand-text">2. In Preparation</h2>
                </div>
                <span class="rounded-sm bg-amber-500/15 text-amber-500 px-2 py-0.5 text-xs font-bold font-mono">
                    {{ $counts['in_prep'] }} Cooking
                </span>
            </div>

            <div class="p-3.5 space-y-3.5 overflow-y-auto max-h-[calc(100vh-250px)]">
                @forelse ($inPrepTickets as $ticket)
                    <div wire:key="ticket-{{ $ticket->id }}"
                        x-show="matchesFilter('{{ $ticket->order->order_type }}', '{{ $ticket->priority }}')"
                        class="rounded-md border transition shadow-xs p-3.5 space-y-3 bg-brand-card {{ $ticket->priority === 'rush' ? 'border-brand-error ring-2 ring-brand-error/20' : ($ticket->urgency_level === 'urgent' ? 'border-brand-error' : ($ticket->urgency_level === 'warning' ? 'border-amber-500/70' : 'border-brand-border')) }}">

                        <!-- Ticket Card Header -->
                        <div class="flex items-start justify-between pb-2 border-b border-dashed border-brand-border">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-base font-black font-mono text-brand-text">{{ $ticket->ticket_number }}</span>
                                    @if ($ticket->priority === 'rush')
                                        <span
                                            class="inline-flex items-center gap-1 rounded-sm bg-brand-error px-1.5 py-0.5 text-[10px] font-black text-white uppercase animate-pulse">
                                            RUSH
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-brand-text-muted mt-0.5">
                                    Cook: <strong class="text-brand-text">{{ $ticket->cook->name ?? 'Chef' }}</strong>
                                    @if ($ticket->order->table_number)
                                        • Table <strong
                                            class="text-brand-text">{{ $ticket->order->table_number }}</strong>
                                    @endif
                                </div>
                            </div>

                            <!-- Elapsed Cook Timer -->
                            <div class="text-right">
                                <span
                                    class="inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-[11px] font-mono font-bold {{ $ticket->urgency_level === 'urgent' ? 'bg-brand-error/15 text-brand-error border border-brand-error/30' : ($ticket->urgency_level === 'warning' ? 'bg-amber-500/15 text-amber-500 border border-amber-500/30' : 'bg-brand-bg text-brand-text-muted border border-brand-border') }}">
                                    🍳 {{ $ticket->elapsed_minutes }}m
                                </span>
                                <div class="text-[10px] text-brand-text-muted mt-0.5 font-mono">
                                    Started: {{ $ticket->started_at ? $ticket->started_at->format('h:i A') : '--:--' }}
                                </div>
                            </div>
                        </div>

                        <!-- Dishes Checklist (Item Checkoffs with 0ms Instant Optimistic Checkbox) -->
                        <div class="space-y-1.5 py-1">
                            @foreach ($ticket->items as $item)
                                <div wire:key="kitchen-item-{{ $item->id }}"
                                    x-data="{ isDone: {{ $item->status === 'done' ? 'true' : 'false' }} }"
                                    x-on:click="isDone = !isDone; $wire.toggleItem({{ $item->id }})"
                                    :class="isDone ? 'bg-brand-bg/70 border-brand-border/40 opacity-60' : 'bg-brand-bg border-brand-border hover:border-brand-primary/50'"
                                    class="group flex items-start justify-between rounded-sm p-2 text-xs cursor-pointer border transition select-none">

                                    <div class="flex items-start gap-2 flex-1 pr-2">
                                        <!-- Custom Checkbox Icon (Instant Optimistic Feedback) -->
                                        <div
                                            :class="isDone ? 'bg-brand-success border-brand-success text-white' : 'border-brand-border group-hover:border-brand-primary'"
                                            class="mt-0.5 h-4 w-4 shrink-0 rounded-xs border flex items-center justify-center transition">
                                            <template x-if="isDone">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </template>
                                        </div>

                                        <div>
                                            <div class="flex items-center gap-1">
                                                <span
                                                    class="font-mono font-bold text-brand-text">{{ $item->orderItem->quantity }}x</span>
                                                <span
                                                    :class="isDone ? 'line-through text-brand-text-muted' : 'text-brand-text'"
                                                    class="font-semibold">
                                                    {{ $item->orderItem->meal_name }}
                                                </span>
                                            </div>

                                            @if ($item->orderItem->special_instructions)
                                                <div
                                                    class="mt-1 flex items-center gap-1 rounded-xs bg-amber-500/15 border border-amber-500/30 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400">
                                                    <span>⚠️</span>
                                                    <span>{{ $item->orderItem->special_instructions }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <span
                                        :class="isDone ? 'text-brand-success font-bold' : 'text-amber-500 font-semibold'"
                                        class="text-[10px] font-mono uppercase">
                                        {{ $item->status === 'done' ? 'Done' : 'Cooking' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <!-- Ticket Action Controls -->
                        <div class="pt-2 border-t border-brand-border flex items-center gap-2">
                            <button type="button" wire:click="markReady({{ $ticket->id }})"
                                wire:loading.attr="disabled"
                                wire:target="markReady({{ $ticket->id }})"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-md bg-brand-success hover:bg-brand-success/90 disabled:opacity-60 text-white py-2 text-xs font-bold uppercase tracking-wider transition cursor-pointer shadow-2xs">
                                <span wire:loading.remove wire:target="markReady({{ $ticket->id }})" class="inline-flex items-center gap-1.5">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Mark Ready</span>
                                </span>
                                <span wire:loading wire:target="markReady({{ $ticket->id }})" class="inline-flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>Plating...</span>
                                </span>
                            </button>

                            <button type="button" wire:click="togglePriority({{ $ticket->id }})"
                                wire:loading.attr="disabled"
                                wire:target="togglePriority({{ $ticket->id }})"
                                title="Toggle Rush Priority"
                                class="rounded-md border border-brand-border hover:bg-brand-bg p-2 text-brand-text-muted hover:text-brand-error transition cursor-pointer">
                                🔥
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-center text-brand-text-muted">
                        <div class="h-10 w-10 rounded-full bg-brand-bg flex items-center justify-center mb-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-brand-text">No Dishes Cooking</p>
                        <p class="text-[11px] text-brand-text-muted mt-0.5">Claim a ticket from the left to start
                            preparation</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 3: Ready to Serve (Pass / Runner Pickup) -->
        <div class="flex flex-col rounded-md border border-brand-border bg-brand-card shadow-2xs">
            <div class="flex items-center justify-between p-3.5 border-b border-brand-border bg-brand-bg/50">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-brand-success"></span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-brand-text">3. Ready to Serve</h2>
                </div>
                <span
                    class="rounded-sm bg-brand-success/15 text-brand-success px-2 py-0.5 text-xs font-bold font-mono">
                    {{ $counts['ready'] }} Ready
                </span>
            </div>

            <div class="p-3.5 space-y-3.5 overflow-y-auto max-h-[calc(100vh-250px)]">
                @forelse ($readyTickets as $ticket)
                    <div wire:key="ticket-{{ $ticket->id }}"
                        x-show="matchesFilter('{{ $ticket->order->order_type }}', '{{ $ticket->priority }}')"
                        class="rounded-md border border-brand-success/40 bg-brand-card p-3.5 space-y-3 shadow-xs">

                        <!-- Ticket Card Header -->
                        <div class="flex items-start justify-between pb-2 border-b border-dashed border-brand-border">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-base font-black font-mono text-brand-text">{{ $ticket->ticket_number }}</span>
                                    <span
                                        class="inline-flex items-center rounded-sm bg-brand-success/20 text-brand-success px-1.5 py-0.5 text-[10px] font-bold uppercase">
                                        Plated & Ready
                                    </span>
                                </div>
                                <div class="text-[11px] text-brand-text-muted mt-0.5">
                                    {{ $ticket->order->order_number }}
                                    @if ($ticket->order->table_number)
                                        • Table <strong
                                            class="text-brand-text">{{ $ticket->order->table_number }}</strong>
                                    @endif
                                    • <span
                                        class="uppercase font-semibold">{{ $ticket->order->order_type_label }}</span>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-[10px] font-mono text-brand-text-muted block">
                                    Ready at {{ $ticket->ready_at ? $ticket->ready_at->format('h:i A') : '--:--' }}
                                </span>
                            </div>
                        </div>

                        <!-- Items Summary -->
                        <div class="space-y-1 py-1 text-xs text-brand-text-muted">
                            @foreach ($ticket->items as $item)
                                <div class="flex justify-between">
                                    <span>
                                        <strong
                                            class="text-brand-text font-mono">{{ $item->orderItem->quantity }}x</strong>
                                        {{ $item->orderItem->meal_name }}
                                    </span>
                                    <span class="text-brand-success text-[10px] font-bold">✓</span>
                                </div>
                            @endforeach
                        </div>

                        <!-- Bump / Mark Served Action -->
                        <div class="pt-2 border-t border-brand-border">
                            <button type="button" wire:click="markServed({{ $ticket->id }})"
                                wire:loading.attr="disabled"
                                wire:target="markServed({{ $ticket->id }})"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-md bg-brand-secondary hover:bg-brand-secondary-hover disabled:opacity-60 text-white py-2 text-xs font-bold uppercase tracking-wider transition cursor-pointer shadow-2xs">
                                <span wire:loading.remove wire:target="markServed({{ $ticket->id }})" class="inline-flex items-center gap-1.5">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Bump Ticket</span>
                                </span>
                                <span wire:loading wire:target="markServed({{ $ticket->id }})" class="inline-flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>Serving...</span>
                                </span>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-center text-brand-text-muted">
                        <div class="h-10 w-10 rounded-full bg-brand-bg flex items-center justify-center mb-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-brand-text">Pass is Clear</p>
                        <p class="text-[11px] text-brand-text-muted mt-0.5">Plated tickets ready for pickup will appear
                            here</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recall / History Slide-Over Drawer (Instant 0ms Client-Side Alpine Drawer) -->
    <div x-show="showRecallDrawer" x-cloak class="fixed inset-0 z-50 overflow-hidden" role="dialog"
        aria-modal="true" x-on:keydown.escape.window="showRecallDrawer = false">
        <!-- Backdrop with 150ms client fade -->
        <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" x-show="showRecallDrawer"
            x-transition:enter="ease-out duration-150" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-100"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            x-on:click="showRecallDrawer = false"></div>

        <!-- Slide-over panel container -->
        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div x-show="showRecallDrawer" x-transition:enter="transform transition ease-out duration-200"
                x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in duration-150"
                x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                class="w-screen max-w-md bg-brand-card border-l border-brand-border shadow-2xl flex flex-col">
                <div class="p-4 border-b border-brand-border flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-black uppercase text-brand-text">Recently Served Tickets</h3>
                        <p class="text-xs text-brand-text-muted">Recall an order if bumped by mistake</p>
                    </div>
                    <button type="button" x-on:click="showRecallDrawer = false"
                        class="rounded-sm p-1 text-brand-text-muted hover:text-brand-text cursor-pointer">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 overflow-y-auto flex-1 space-y-3">
                    @forelse ($recentServedTickets as $servedTicket)
                        <div class="rounded-md border border-brand-border bg-brand-bg/50 p-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-mono font-bold text-sm text-brand-text">{{ $servedTicket->ticket_number }}</span>
                                    <span
                                        class="text-xs text-brand-text-muted">{{ $servedTicket->order->order_number }}</span>
                                </div>
                                <button type="button" wire:click="recallTicket({{ $servedTicket->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="recallTicket({{ $servedTicket->id }})"
                                    class="rounded-sm bg-brand-primary hover:bg-brand-primary-hover disabled:opacity-60 text-white px-2.5 py-1 text-xs font-semibold transition cursor-pointer">
                                    <span wire:loading.remove wire:target="recallTicket({{ $servedTicket->id }})">↩ Recall</span>
                                    <span wire:loading wire:target="recallTicket({{ $servedTicket->id }})">Restoring...</span>
                                </button>
                            </div>
                            <div class="text-[11px] text-brand-text-muted">
                                Served at:
                                {{ $servedTicket->served_at ? $servedTicket->served_at->format('h:i A') : '--:--' }}
                                • Cook: {{ $servedTicket->cook->name ?? 'Chef' }}
                            </div>
                            <div class="text-xs text-brand-text font-medium pt-1 border-t border-brand-border/40">
                                {{ $servedTicket->items->pluck('orderItem.meal_name')->join(', ') }}
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-brand-text-muted">
                            No recently served tickets.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
