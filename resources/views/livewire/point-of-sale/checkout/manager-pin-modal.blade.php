<x-ui.modal name="manager-pin-modal" title="Manager Authorization Required" maxWidth="sm">
    <div x-data="{
        empId: '',
        pin: '',
        reason: '',
        isProcessing: false,
        error: '{{ $errorMessage ?? '' }}',
        orderId: {{ $orderId ?? 'null' }},
        orderNumber: '',
    
        init() {
            this.slideOver = document.getElementById('order-progress-drawer');
            window.addEventListener('open-manager-pin', (e) => {
                this.orderId = e.detail?.orderId ?? null;
                this.orderNumber = e.detail?.orderNumber ?? '';
                this.empId = '';
                this.pin = '';
                this.reason = '';
                this.error = '';
                this.isProcessing = false;
            });
        },
    
        submit() {
            if (!this.empId.trim() || !this.pin.trim() || !this.reason.trim()) {
                this.error = 'Please enter Manager Employee ID, 4-digit PIN, and a cancellation reason.';
                return;
            }
            this.isProcessing = true;
            this.error = '';
            $wire.submitVoidRequest({
                    manager_id: this.empId,
                    manager_pin: this.pin,
                    reason: this.reason
                }).then((success) => {
                    if (success) {
                        $dispatch('close-modal', 'manager-pin-modal');
                    } else {
                        this.error = $wire.errorMessage || 'Authorization failed. Please check manager credentials.';
                    }
                    this.isProcessing = false;
                })
                .catch((err) => {
                    this.error = err?.message || 'Server error occurred during void request.';
                    this.isProcessing = false;
                });
        }
    }" class="space-y-4">

        <!-- Error Notice -->
        <div x-show="error" x-cloak
            class="rounded-md border border-brand-error/30 bg-brand-error/10 p-3 text-xs font-semibold text-brand-error flex items-start gap-2">
            <svg class="h-4 w-4 shrink-0 text-brand-error mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span x-text="error"></span>
        </div>

        <!-- Order Context -->
        <div class="text-sm text-brand-text-muted" x-show="orderId" x-cloak>
            <p class="font-semibold text-brand-text">Voiding Order #<span x-text="orderNumber || orderId"></span></p>
        </div>

        <!-- Manager Employee ID -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-brand-text mb-1.5">
                Manager Employee ID
            </label>
            <input type="text" x-model="empId" placeholder="e.g. B01-MGR-001"
                class="block w-full rounded-md border border-brand-border bg-brand-bg/60 px-3.5 py-2 text-sm font-medium text-brand-text shadow-2xs placeholder:text-brand-text-muted/50 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 transition uppercase">
        </div>

        <!-- 4-Digit PIN -->
        <div>
            <label for="manager-pin-input" class="block text-xs font-bold uppercase tracking-wider text-brand-text mb-1.5">
                4-Digit Manager PIN
            </label>
            <div x-on:click="$refs.pinInput.focus()"
                class="relative flex items-center justify-center gap-3 py-2.5 bg-brand-bg/70 rounded-md border border-brand-border cursor-text focus-within:border-brand-primary focus-within:ring-2 focus-within:ring-brand-primary/20 transition">
                <template x-for="i in 4" :key="i">
                    <div class="h-4 w-4 rounded-full transition-all duration-150"
                        :class="pin.length >= i ?
                            'bg-brand-primary ring-4 ring-brand-secondary/30 scale-110' :
                            'border-2 border-brand-border bg-brand-card'">
                    </div>
                </template>
                <input x-ref="pinInput" x-model="pin" type="password" maxlength="4" inputmode="numeric" pattern="[0-9]*"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-text" id="manager-pin-input" />
            </div>
        </div>

        <!-- Cancellation Reason -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-brand-text mb-1.5">
                Cancellation Reason
            </label>
            <textarea x-model="reason" rows="2" placeholder="e.g., Customer changed mind, wrong items entered"
                class="block w-full rounded-md border border-brand-border bg-brand-bg/60 px-3.5 py-2 text-sm text-brand-text shadow-2xs placeholder:text-brand-text-muted/50 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 transition"></textarea>
        </div>

        <!-- Modal Footer -->
        <div class="pt-3 border-t border-brand-border flex items-center justify-end gap-2.5">
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'manager-pin-modal')">
                Cancel
            </x-ui.button>

            <x-ui.button type="button" variant="primary" x-on:click="submit()" x-bind:disabled="isProcessing">
                <span x-show="!isProcessing">Authorize Void</span>
                <span x-show="isProcessing" x-cloak class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Authorizing...
                </span>
            </x-ui.button>
        </div>
    </div>
</x-ui.modal>
