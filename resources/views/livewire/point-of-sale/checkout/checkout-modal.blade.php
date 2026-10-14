<x-ui.modal name="checkout-modal" title="Finalize Sale & Payment" maxWidth="lg">
    <div x-data="{
        items: @js($items),
        orderType: '{{ $orderType }}',
        tableNumber: '{{ $tableNumber }}',
        customerName: '{{ $customerName }}',
        subtotal: {{ (float) $subtotal }},
        discountAmount: {{ (float) $discountAmount }},
        totalAmount: {{ (float) $totalAmount }},
        notes: '{{ $notes }}',
    
        paymentMethod: 'cash',
        amountTendered: '{{ number_format($totalAmount, 2, '.', '') }}',
        referenceNumber: '',
        errorMessage: '{{ $errorMessage ?? '' }}',
        isProcessing: false,
    
        init() {
            window.addEventListener('open-checkout-client', (e) => {
                const data = e.detail;
                this.items = data.items || [];
                this.orderType = data.order_type || 'dine_in';
                this.tableNumber = data.table_number || '';
                this.customerName = data.customer_name || '';
                this.subtotal = parseFloat(data.subtotal) || 0;
                this.discountAmount = parseFloat(data.discount_amount) || 0;
                this.totalAmount = parseFloat(data.total_amount) || 0;
                this.notes = data.notes || '';
    
                this.paymentMethod = 'cash';
                this.amountTendered = this.totalAmount.toFixed(2);
                this.referenceNumber = '';
                this.errorMessage = '';
                this.isProcessing = false;
            });
        },
    
        get changeDue() {
            const tendered = parseFloat(this.amountTendered) || 0;
            if (tendered < this.totalAmount) return 0.00;
            return Math.round((tendered - this.totalAmount) * 100) / 100;
        },
    
        formatCurrency(num) {
            return '₱' + Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    
        setExactTender() {
            this.amountTendered = this.totalAmount.toFixed(2);
        },
    
        setTenderAmount(amount) {
            this.amountTendered = Number(amount).toFixed(2);
        },
    
        async submitPayment() {
            this.errorMessage = '';
            const tendered = parseFloat(this.amountTendered) || 0;
    
            if (this.paymentMethod === 'cash') {
                if (tendered < this.totalAmount) {
                    this.errorMessage = 'Amount tendered (' + this.formatCurrency(tendered) + ') is less than the total due (' + this.formatCurrency(this.totalAmount) + ').';
                    return;
                }
            } else {
                if (!this.referenceNumber.trim()) {
                    this.errorMessage = 'Reference / Approval Code is required for card and digital payments.';
                    return;
                }
            }
    
            this.isProcessing = true;
    
            try {
                const serverError = await $wire.processCheckoutFromClient({
                    items: this.items,
                    order_type: this.orderType,
                    table_number: this.tableNumber,
                    customer_name: this.customerName,
                    discount_amount: this.discountAmount,
                    notes: this.notes,
                    payment_method: this.paymentMethod,
                    amount_tendered: tendered,
                    reference_number: this.referenceNumber
                });
    
                if (serverError) {
                    this.errorMessage = serverError;
                    this.isProcessing = false;
                }
            } catch (err) {
                this.errorMessage = err.message || 'Payment processing failed.';
                this.isProcessing = false;
            }
        }
    }" class="space-y-4">

        <!-- Order Subtitle / Metadata (Instant Client State) -->
        <div
            class="text-xs text-brand-text-muted -mt-1 pb-2 border-b border-brand-border flex items-center justify-between">
            <span>
                Dining: <strong class="text-brand-text uppercase" x-text="orderType.replace('_', ' ')"></strong>
                <template x-if="tableNumber">
                    <span> • Table: <strong class="font-mono text-brand-text" x-text="tableNumber"></strong></span>
                </template>
                <template x-if="customerName">
                    <span> • Guest: <strong class="text-brand-text" x-text="customerName"></strong></span>
                </template>
            </span>
            <span class="font-mono font-semibold text-brand-text">
                <span x-text="items.length"></span> <span
                    x-text="items.length === 1 ? 'line item' : 'line items'"></span>
            </span>
        </div>

        <!-- Error Notice (Guaranteed Non-Blank) -->
        <div x-show="errorMessage || $wire.errorMessage" x-cloak
            class="rounded-md border border-brand-error/30 bg-brand-error/10 p-3 text-xs font-semibold text-brand-error flex items-start gap-2">
            <svg class="h-4 w-4 shrink-0 text-brand-error mt-0.5" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span x-text="errorMessage || $wire.errorMessage"></span>
        </div>

        <!-- Total Amount Banner (Instant Client Display) -->
        <div class="rounded-md border border-brand-border bg-brand-bg/70 p-4 flex items-center justify-between">
            <div>
                <span class="block text-[11px] font-bold uppercase tracking-wider text-brand-text-muted">Total
                    Due</span>
                <template x-if="discountAmount > 0">
                    <span class="text-[11px] text-brand-success font-medium">
                        Includes <span x-text="formatCurrency(discountAmount)"></span> discount
                    </span>
                </template>
            </div>
            <span class="font-mono text-2xl font-black text-brand-primary dark:text-brand-secondary"
                x-text="formatCurrency(totalAmount)">
            </span>
        </div>

        <!-- Payment Method Selector (Instant 0ms Client Switch) -->
        <div class="space-y-3">
            <label class="block text-xs font-bold uppercase tracking-wider text-brand-text">
                Payment Method
            </label>

            <div class="grid grid-cols-3 gap-2">
                <button type="button" x-on:click="paymentMethod = 'cash'"
                    :class="paymentMethod === 'cash' ?
                        'border-brand-primary bg-brand-primary/10 text-brand-primary dark:border-brand-secondary dark:text-brand-secondary font-bold' :
                        'border-brand-border bg-brand-bg/40 text-brand-text-muted hover:text-brand-text'"
                    class="flex flex-col items-center justify-center gap-1 rounded-md border p-3 text-xs font-semibold transition cursor-pointer select-none">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Cash</span>
                </button>

                <button type="button" x-on:click="paymentMethod = 'credit_card'"
                    :class="paymentMethod === 'credit_card' ?
                        'border-brand-primary bg-brand-primary/10 text-brand-primary dark:border-brand-secondary dark:text-brand-secondary font-bold' :
                        'border-brand-border bg-brand-bg/40 text-brand-text-muted hover:text-brand-text'"
                    class="flex flex-col items-center justify-center gap-1 rounded-md border p-3 text-xs font-semibold transition cursor-pointer select-none">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span>Card</span>
                </button>

                <button type="button" x-on:click="paymentMethod = 'qr_transfer'"
                    :class="paymentMethod === 'qr_transfer' ?
                        'border-brand-primary bg-brand-primary/10 text-brand-primary dark:border-brand-secondary dark:text-brand-secondary font-bold' :
                        'border-brand-border bg-brand-bg/40 text-brand-text-muted hover:text-brand-text'"
                    class="flex flex-col items-center justify-center gap-1 rounded-md border p-3 text-xs font-semibold transition cursor-pointer select-none">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                    <span>QR / E-Wallet</span>
                </button>
            </div>

            <!-- Cash Payment Inputs & Fast Tender (Instant 0ms Buttons) -->
            <div x-show="paymentMethod === 'cash'" class="space-y-3 pt-2">
                <!-- Quick Cash Tender Buttons -->
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" x-on:click="setExactTender()"
                        class="rounded-sm border border-brand-border bg-brand-bg px-2.5 py-1 text-xs font-semibold text-brand-text hover:bg-brand-card transition cursor-pointer">
                        Exact (<span x-text="formatCurrency(totalAmount)"></span>)
                    </button>
                    <button type="button" x-on:click="setTenderAmount(100)"
                        class="rounded-sm border border-brand-border bg-brand-bg px-2.5 py-1 text-xs font-mono font-semibold text-brand-text hover:bg-brand-card transition cursor-pointer">
                        ₱100
                    </button>
                    <button type="button" x-on:click="setTenderAmount(200)"
                        class="rounded-sm border border-brand-border bg-brand-bg px-2.5 py-1 text-xs font-mono font-semibold text-brand-text hover:bg-brand-card transition cursor-pointer">
                        ₱200
                    </button>
                    <button type="button" x-on:click="setTenderAmount(500)"
                        class="rounded-sm border border-brand-border bg-brand-bg px-2.5 py-1 text-xs font-mono font-semibold text-brand-text hover:bg-brand-card transition cursor-pointer">
                        ₱500
                    </button>
                    <button type="button" x-on:click="setTenderAmount(1000)"
                        class="rounded-sm border border-brand-border bg-brand-bg px-2.5 py-1 text-xs font-mono font-semibold text-brand-text hover:bg-brand-card transition cursor-pointer">
                        ₱1,000
                    </button>
                </div>

                <!-- Tendered Input & Instant Change Box -->
                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-brand-text-muted mb-1">
                            Amount Tendered (₱)
                        </label>
                        <input type="number" step="0.01" min="0" x-model="amountTendered"
                            class="w-full rounded-md border border-brand-border bg-brand-bg px-3 py-2 font-mono text-base font-bold text-brand-text focus:border-brand-primary focus:bg-brand-card focus:outline-none focus:ring-1 focus:ring-brand-primary/20 transition">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-brand-text-muted mb-1">
                            Change Given
                        </label>
                        <div :class="parseFloat(amountTendered) >= totalAmount ? 'text-brand-success' : 'text-brand-error'"
                            class="rounded-md border border-brand-border bg-brand-bg px-3 py-2 font-mono text-base font-bold"
                            x-text="formatCurrency(changeDue)">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card or QR Reference Input -->
            <div x-show="paymentMethod !== 'cash'" x-cloak class="pt-2">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-brand-text mb-1">
                    Transaction / Trace Reference #
                </label>
                <input type="text" x-model="referenceNumber" placeholder="e.g. TRACE-884920 or GCash Ref 90281"
                    class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text font-mono focus:border-brand-primary focus:bg-brand-card focus:outline-none focus:ring-1 focus:ring-brand-primary/20 transition">
            </div>
        </div>

        <!-- Modal Footer Actions (Instant 0ms Client Dismiss) -->
        <div class="pt-4 border-t border-brand-border flex items-center justify-end gap-2.5">
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'checkout-modal')">
                Cancel
            </x-ui.button>

            <x-ui.button type="button" variant="primary" x-on:click="submitPayment()"
                x-bind:disabled="isProcessing">
                <span x-show="!isProcessing">Complete Sale &amp; Print</span>
                <span x-show="isProcessing" x-cloak class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Processing...
                </span>
            </x-ui.button>
        </div>
    </div>
</x-ui.modal>
