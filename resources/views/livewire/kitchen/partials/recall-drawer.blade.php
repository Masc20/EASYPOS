<div x-show="showRecallDrawer" x-cloak class="fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true"
    x-on:keydown.escape.window="showRecallDrawer = false">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
        x-show="showRecallDrawer"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="showRecallDrawer = false"></div>

    <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div x-show="showRecallDrawer"
            x-transition:enter="transform transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="w-screen max-w-md bg-brand-card border-l border-brand-border shadow-2xl flex flex-col">
            <div class="p-4 border-b border-brand-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black uppercase text-brand-text">Recently Served Tickets</h3>
                    <p class="text-xs text-brand-text-muted">Recall an order if bumped by mistake</p>
                </div>
                <button type="button" x-on:click="showRecallDrawer = false"
                    class="rounded-sm p-1 text-brand-text-muted hover:text-brand-text cursor-pointer">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-4 overflow-y-auto flex-1 space-y-3">
                @forelse ($recentServedTickets as $servedTicket)
                    <div class="rounded-md border border-brand-border bg-brand-bg/50 p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-sm text-brand-text">{{ $servedTicket->ticket_number }}</span>
                                <span class="text-xs text-brand-text-muted">{{ $servedTicket->order->order_number }}</span>
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
                            Served at: {{ $servedTicket->served_at ? $servedTicket->served_at->format('h:i A') : '--:--' }}
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
