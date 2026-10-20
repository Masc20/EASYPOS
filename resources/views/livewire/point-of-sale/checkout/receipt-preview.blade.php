<x-ui.modal name="receipt-modal" title="Official Sales Receipt" maxWidth="sm">
    @if ($order)
        <x-pos.receipt-slip :order="$order" :showActions="false" />

        <!-- Action Buttons (Print & Instant 0ms New Sale) -->
        <div class="mt-4 pt-3 border-t border-brand-border flex items-center justify-between gap-2">
            <x-ui.button type="button"
                variant="secondary"
                onclick="window.print()"
                class="flex-1 cursor-pointer">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>Print Receipt</span>
            </x-ui.button>

            <x-ui.button type="button"
                variant="primary"
                x-on:click="$dispatch('close-modal', 'receipt-modal')"
                class="flex-1 cursor-pointer">
                <span>New Sale</span>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </x-ui.button>
        </div>
    @endif
</x-ui.modal>
