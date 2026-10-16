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

    @include('livewire.kitchen.partials.header')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 1. New Orders -->
        <div class="flex flex-col rounded-md border border-brand-border bg-brand-card shadow-2xs">
            <div class="flex items-center justify-between p-3.5 border-b border-brand-border bg-brand-bg/50">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-brand-secondary"></span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-brand-text">1. New Orders</h2>
                </div>
                <span class="rounded-sm bg-brand-secondary/15 text-brand-secondary px-2 py-0.5 text-xs font-bold font-mono">
                    {{ $counts['new'] }} Tickets
                </span>
            </div>

            <div class="p-3.5 space-y-3.5 overflow-y-auto max-h-[calc(100vh-250px)]">
                @forelse ($newTickets as $ticket)
                    <div wire:key="ticket-{{ $ticket->id }}"
                        x-show="matchesFilter('{{ $ticket->order->order_type }}', '{{ $ticket->priority }}')"
                        class="rounded-md border transition shadow-xs p-3.5 space-y-3 bg-brand-card {{ $ticket->priority === 'rush' ? 'border-brand-error ring-2 ring-brand-error/20' : ($ticket->urgency_level === 'urgent' ? 'border-brand-error' : ($ticket->urgency_level === 'warning' ? 'border-amber-500/70' : 'border-brand-border')) }}">

                        <div class="flex items-start justify-between pb-2 border-b border-dashed border-brand-border">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-black font-mono text-brand-text">{{ $ticket->ticket_number }}</span>
                                    @if ($ticket->priority === 'rush')
                                        <span class="inline-flex items-center gap-1 rounded-sm bg-brand-error px-1.5 py-0.5 text-[10px] font-black text-white uppercase animate-pulse">
                                            RUSH
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-brand-text-muted mt-0.5">
                                    {{ $ticket->order->order_number }}
                                    @if ($ticket->order->table_number)
                                        • <strong class="text-brand-text">Table {{ $ticket->order->table_number }}</strong>
                                    @endif
                                    • <span class="uppercase font-semibold">{{ $ticket->order->order_type_label }}</span>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-[11px] font-mono font-bold {{ $ticket->urgency_level === 'urgent' ? 'bg-brand-error/15 text-brand-error border border-brand-error/30' : ($ticket->urgency_level === 'warning' ? 'bg-amber-500/15 text-amber-500 border border-amber-500/30' : 'bg-brand-bg text-brand-text-muted border border-brand-border') }}">
                                    ⏱ {{ $ticket->elapsed_minutes }}m ago
                                </span>
                                <div class="text-[10px] text-brand-text-muted mt-0.5 font-mono">
                                    {{ $ticket->created_at->format('h:i A') }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 py-1">
                            @foreach ($ticket->items as $item)
                                <div class="flex items-start justify-between text-xs">
                                    <div class="flex-1 pr-2">
                                        <span class="font-bold text-brand-text mr-1.5 font-mono text-sm">{{ $item->orderItem->quantity }}x</span>
                                        <span class="font-semibold text-brand-text">{{ $item->orderItem->meal_name }}</span>

                                        @if ($item->orderItem->special_instructions)
                                            <div class="mt-1 flex items-center gap-1 rounded-xs bg-amber-500/15 border border-amber-500/30 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400">
                                                <span>⚠️ NOTE:</span>
                                                <span>{{ $item->orderItem->special_instructions }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-brand-text">No New Tickets</p>
                        <p class="text-[11px] text-brand-text-muted mt-0.5">Orders sent from the register will appear here</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- 2. In Preparation -->
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

                        <div class="flex items-start justify-between pb-2 border-b border-dashed border-brand-border">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-black font-mono text-brand-text">{{ $ticket->ticket_number }}</span>
                                    @if ($ticket->priority === 'rush')
                                        <span class="inline-flex items-center gap-1 rounded-sm bg-brand-error px-1.5 py-0.5 text-[10px] font-black text-white uppercase animate-pulse">
                                            RUSH
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-brand-text-muted mt-0.5">
                                    Cook: <strong class="text-brand-text">{{ $ticket->cook->name ?? 'Chef' }}</strong>
                                    @if ($ticket->order->table_number)
                                        • Table <strong class="text-brand-text">{{ $ticket->order->table_number }}</strong>
                                    @endif
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-[11px] font-mono font-bold {{ $ticket->urgency_level === 'urgent' ? 'bg-brand-error/15 text-brand-error border border-brand-error/30' : ($ticket->urgency_level === 'warning' ? 'bg-amber-500/15 text-amber-500 border border-amber-500/30' : 'bg-brand-bg text-brand-text-muted border border-brand-border') }}">
                                    🍳 {{ $ticket->elapsed_minutes }}m
                                </span>
                                <div class="text-[10px] text-brand-text-muted mt-0.5 font-mono">
                                    Started: {{ $ticket->started_at ? $ticket->started_at->format('h:i A') : '--:--' }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5 py-1">
                            @foreach ($ticket->items as $item)
                                <div wire:key="kitchen-item-{{ $item->id }}"
                                    x-data="{ isDone: {{ $item->status === 'done' ? 'true' : 'false' }} }"
                                    x-on:click="isDone = !isDone; $wire.toggleItem({{ $item->id }})"
                                    :class="isDone ? 'bg-brand-bg/70 border-brand-border/40 opacity-60' : 'bg-brand-bg border-brand-border hover:border-brand-primary/50'"
                                    class="group flex items-start justify-between rounded-sm p-2 text-xs cursor-pointer border transition select-none">

                                    <div class="flex items-start gap-2 flex-1 pr-2">
                                        <div :class="isDone ? 'bg-brand-success border-brand-success text-white' : 'border-brand-border group-hover:border-brand-primary'"
                                            class="mt-0.5 h-4 w-4 shrink-0 rounded-xs border flex items-center justify-center transition">
                                            <template x-if="isDone">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </template>
                                        </div>

                                        <div>
                                            <div class="flex items-center gap-1">
                                                <span class="font-mono font-bold text-brand-text">{{ $item->orderItem->quantity }}x</span>
                                                <span :class="isDone ? 'line-through text-brand-text-muted' : 'text-brand-text'" class="font-semibold">
                                                    {{ $item->orderItem->meal_name }}
                                                </span>
                                            </div>

                                            @if ($item->orderItem->special_instructions)
                                                <div class="mt-1 flex items-center gap-1 rounded-xs bg-amber-500/15 border border-amber-500/30 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400">
                                                    <span>⚠️</span>
                                                    <span>{{ $item->orderItem->special_instructions }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <span :class="isDone ? 'text-brand-success font-bold' : 'text-amber-500 font-semibold'"
                                        class="text-[10px] font-mono uppercase">
                                        {{ $item->status === 'done' ? 'Done' : 'Cooking' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-2 border-t border-brand-border flex items-center gap-2">
                            <button type="button" wire:click="markReady({{ $ticket->id }})"
                                wire:loading.attr="disabled"
                                wire:target="markReady({{ $ticket->id }})"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-md bg-brand-success hover:bg-brand-success/90 disabled:opacity-60 text-white py-2 text-xs font-bold uppercase tracking-wider transition cursor-pointer shadow-2xs">
                                <span wire:loading.remove wire:target="markReady({{ $ticket->id }})" class="inline-flex items-center gap-1.5">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-brand-text">No Dishes Cooking</p>
                        <p class="text-[11px] text-brand-text-muted mt-0.5">Claim a ticket from the left to start preparation</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- 3. Ready to Serve -->
        <div class="flex flex-col rounded-md border border-brand-border bg-brand-card shadow-2xs">
            <div class="flex items-center justify-between p-3.5 border-b border-brand-border bg-brand-bg/50">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-brand-success"></span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-brand-text">3. Ready to Serve</h2>
                </div>
                <span class="rounded-sm bg-brand-success/15 text-brand-success px-2 py-0.5 text-xs font-bold font-mono">
                    {{ $counts['ready'] }} Ready
                </span>
            </div>

            <div class="p-3.5 space-y-3.5 overflow-y-auto max-h-[calc(100vh-250px)]">
                @forelse ($readyTickets as $ticket)
                    <div wire:key="ticket-{{ $ticket->id }}"
                        x-show="matchesFilter('{{ $ticket->order->order_type }}', '{{ $ticket->priority }}')"
                        class="rounded-md border border-brand-success/40 bg-brand-card p-3.5 space-y-3 shadow-xs">

                        <div class="flex items-start justify-between pb-2 border-b border-dashed border-brand-border">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-black font-mono text-brand-text">{{ $ticket->ticket_number }}</span>
                                    <span class="inline-flex items-center rounded-sm bg-brand-success/20 text-brand-success px-1.5 py-0.5 text-[10px] font-bold uppercase">
                                        Plated & Ready
                                    </span>
                                </div>
                                <div class="text-[11px] text-brand-text-muted mt-0.5">
                                    {{ $ticket->order->order_number }}
                                    @if ($ticket->order->table_number)
                                        • Table <strong class="text-brand-text">{{ $ticket->order->table_number }}</strong>
                                    @endif
                                    • <span class="uppercase font-semibold">{{ $ticket->order->order_type_label }}</span>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-[10px] font-mono text-brand-text-muted block">
                                    Ready at {{ $ticket->ready_at ? $ticket->ready_at->format('h:i A') : '--:--' }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-1 py-1 text-xs text-brand-text-muted">
                            @foreach ($ticket->items as $item)
                                <div class="flex justify-between">
                                    <span>
                                        <strong class="text-brand-text font-mono">{{ $item->orderItem->quantity }}x</strong>
                                        {{ $item->orderItem->meal_name }}
                                    </span>
                                    <span class="text-brand-success text-[10px] font-bold">✓</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-2 border-t border-brand-border">
                            <button type="button" wire:click="markServed({{ $ticket->id }})"
                                wire:loading.attr="disabled"
                                wire:target="markServed({{ $ticket->id }})"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-md bg-brand-secondary hover:bg-brand-secondary-hover disabled:opacity-60 text-white py-2 text-xs font-bold uppercase tracking-wider transition cursor-pointer shadow-2xs">
                                <span wire:loading.remove wire:target="markServed({{ $ticket->id }})" class="inline-flex items-center gap-1.5">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-brand-text">Pass is Clear</p>
                        <p class="text-[11px] text-brand-text-muted mt-0.5">Plated tickets ready for pickup will appear here</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @include('livewire.kitchen.partials.recall-drawer')
</div>
