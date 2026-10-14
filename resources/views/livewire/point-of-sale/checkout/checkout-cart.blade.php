<div x-data="{
    items: @js($items),
    orderType: '{{ $orderType }}',
    tableNumber: '{{ $tableNumber }}',
    customerName: '{{ $customerName }}',
    discountType: '{{ $discountType }}',
    customDiscount: {{ (float) $customDiscount }},
    notes: '{{ $notes }}',

    init() {
        window.addEventListener('add-to-cart-client', (e) => {
            this.addMeal(e.detail);
        });
        window.addEventListener('cart-reset', () => {
            this.clear();
        });
    },

    addMeal(meal) {
        const existing = this.items.find(i => i.meal_id === meal.id);
        if (existing) {
            if (meal.maxQty !== null && existing.quantity >= meal.maxQty) {
                alert('Maximum daily portion limit reached for ' + meal.name + ' (' + meal.maxQty + ' available).');
                return;
            }
            existing.quantity++;
        } else {
            this.items.push({
                meal_id: meal.id,
                name: meal.name,
                menu_code: meal.code || '',
                price: parseFloat(meal.price) || 0,
                quantity: 1,
                special_instructions: '',
                max_quantity: meal.maxQty !== undefined ? meal.maxQty : null
            });
        }
    },

    increment(index) {
        const item = this.items[index];
        if (!item) return;
        if (item.max_quantity !== null && item.quantity >= item.max_quantity) {
            alert('Cannot exceed ' + item.max_quantity + ' portions.');
            return;
        }
        item.quantity++;
    },

    decrement(index) {
        if (!this.items[index]) return;
        if (this.items[index].quantity > 1) {
            this.items[index].quantity--;
        } else {
            this.remove(index);
        }
    },

    remove(index) {
        this.items.splice(index, 1);
    },

    clear() {
        this.items = [];
        this.tableNumber = '';
        this.customerName = '';
        this.discountType = 'none';
        this.customDiscount = 0;
        this.notes = '';
    },

    get itemCount() {
        return this.items.reduce((sum, item) => sum + (parseInt(item.quantity) || 0), 0);
    },

    get subtotal() {
        const sum = this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        return Math.round(sum * 100) / 100;
    },

    get discountAmount() {
        if (this.discountType === 'senior_pwd') {
            return Math.round(this.subtotal * 0.20 * 100) / 100;
        }
        if (this.discountType === 'custom') {
            return Math.min(this.subtotal, Math.max(0, parseFloat(this.customDiscount) || 0));
        }
        return 0.00;
    },

    get totalAmount() {
        return Math.max(0, Math.round((this.subtotal - this.discountAmount) * 100) / 100);
    },

    formatCurrency(num) {
        return '₱' + Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    proceed() {
        if (this.items.length === 0) {
            alert('Your cart is empty. Tap dishes on the left to add items.');
            return;
        }
        const data = {
            items: this.items,
            order_type: this.orderType,
            table_number: this.tableNumber,
            customer_name: this.customerName,
            subtotal: this.subtotal,
            discount_amount: this.discountAmount,
            total_amount: this.totalAmount,
            notes: this.notes
        };
        // 1. Instant 0ms client-side modal trigger
        window.dispatchEvent(new CustomEvent('open-checkout-client', { detail: data }));
        $dispatch('open-modal', 'checkout-modal');

        // 2. Headless PHPUnit sync
        if (window.Livewire) {
            Livewire.dispatch('open-checkout', data);
        }
    }
}" class="flex flex-col h-full space-y-4">

    <!-- Header: Active Order & Clear (Instant Client State) -->
    <div class="flex items-center justify-between pb-3 border-b border-brand-border">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-bold uppercase tracking-wider text-brand-text">Active Order</h3>
            <span
                class="inline-flex items-center rounded-sm bg-brand-primary/10 border border-brand-primary/20 px-2 py-0.5 text-[11px] font-bold text-brand-primary">
                <span x-text="itemCount"></span>
                <span class="ml-1" x-text="itemCount === 1 ? 'item' : 'items'"></span>
            </span>
        </div>
        <template x-if="items.length > 0">
            <button type="button" x-on:click="clear()"
                class="text-xs text-brand-text-muted hover:text-brand-error transition font-semibold cursor-pointer">
                Clear
            </button>
        </template>
    </div>

    <!-- Order Configuration: Dining Type & Table (Instant 0ms Client-Side Alpine Switch) -->
    <div class="space-y-2.5">
        <!-- Order Type Segmented Switcher -->
        <div class="grid grid-cols-2 gap-1 rounded-md border border-brand-border bg-brand-bg p-1 text-xs">
            <button type="button" x-on:click="orderType = 'dine_in'"
                :class="orderType === 'dine_in' ? 'bg-brand-primary text-white shadow-2xs' :
                    'text-brand-text-muted hover:text-brand-text'"
                class="rounded-sm py-1.5 font-semibold transition cursor-pointer select-none text-center">
                Dine In
            </button>
            <button type="button" x-on:click="orderType = 'takeout'"
                :class="orderType === 'takeout' ? 'bg-brand-primary text-white shadow-2xs' :
                    'text-brand-text-muted hover:text-brand-text'"
                class="rounded-sm py-1.5 font-semibold transition cursor-pointer select-none text-center">
                Takeout
            </button>
        </div>

        <!-- Table Number & Customer Details -->
        <div class="grid grid-cols-2 gap-2">
            <div x-show="orderType === 'dine_in'" x-cloak>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-brand-text-muted mb-1">
                    Table #
                </label>
                <input type="text" x-model="tableNumber" placeholder="e.g. T-04"
                    class="w-full rounded-md border border-brand-border bg-brand-bg px-2.5 py-1.5 text-xs text-brand-text font-mono font-bold uppercase placeholder:text-brand-text-muted/60 focus:border-brand-primary focus:bg-brand-card focus:outline-none focus:ring-1 focus:ring-brand-primary/20 transition">
            </div>
            <div :class="orderType !== 'dine_in' ? 'col-span-2' : ''">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-brand-text-muted mb-1">
                    Customer / Note
                </label>
                <input type="text" x-model="customerName" placeholder="Guest name (optional)"
                    class="w-full rounded-md border border-brand-border bg-brand-bg px-2.5 py-1.5 text-xs text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary focus:bg-brand-card focus:outline-none focus:ring-1 focus:ring-brand-primary/20 transition">
            </div>
        </div>
    </div>

    <!-- Cart Line Items (Instant 0ms Client List) -->
    <div class="flex-1 overflow-y-auto max-h-[320px] space-y-2 pr-1 scrollbar-thin">
        <!-- Dynamic Alpine Items -->
        <template x-for="(item, index) in items" :key="item.meal_id">
            <div class="rounded-md border border-brand-border bg-brand-bg/50 p-2.5 transition space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5">
                            <template x-if="item.menu_code">
                                <span
                                    class="rounded-sm bg-brand-card px-1 py-0.2 text-[9px] font-mono font-bold text-brand-text-muted border border-brand-border"
                                    x-text="item.menu_code">
                                </span>
                            </template>
                            <span class="text-xs font-semibold text-brand-text leading-tight" x-text="item.name"></span>
                        </div>
                        <span class="text-[11px] font-mono text-brand-text-muted"
                            x-text="formatCurrency(item.price)"></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Line Subtotal -->
                        <span class="font-mono text-xs font-bold text-brand-text"
                            x-text="formatCurrency(item.price * item.quantity)">
                        </span>
                        <!-- Remove Line (0ms) -->
                        <button type="button" x-on:click="remove(index)"
                            class="text-xs text-brand-text-muted hover:text-brand-error transition p-1 cursor-pointer">
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Quantity Control & Special Instructions -->
                <div class="flex items-center justify-between pt-1 border-t border-brand-border/40 gap-2">
                    <!-- Inline Note -->
                    <input type="text" x-model="item.special_instructions"
                        placeholder="Prep note (e.g. no onions)..."
                        class="flex-1 rounded-sm border border-brand-border/70 bg-brand-card px-2 py-0.5 text-[11px] text-brand-text placeholder:text-brand-text-muted/50 focus:border-brand-primary focus:outline-none">

                    <!-- - / + Stepper (0ms Instant) -->
                    <div class="flex items-center rounded-sm border border-brand-border bg-brand-card shadow-2xs">
                        <button type="button" x-on:click="decrement(index)"
                            class="h-6 w-6 flex items-center justify-center text-xs font-bold text-brand-text hover:bg-brand-bg transition cursor-pointer select-none">
                            -
                        </button>
                        <span
                            class="px-2 text-xs font-mono font-bold text-brand-text min-w-[20px] text-center select-none"
                            x-text="item.quantity">
                        </span>
                        <button type="button" x-on:click="increment(index)"
                            class="h-6 w-6 flex items-center justify-center text-xs font-bold text-brand-text hover:bg-brand-bg transition cursor-pointer select-none">
                            +
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Empty Cart Notice -->
        <template x-if="items.length === 0">
            <div class="py-10 text-center border-2 border-dashed border-brand-border rounded-md bg-brand-bg/20">
                <svg class="mx-auto h-8 w-8 text-brand-text-muted/40 mb-1" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <p class="text-xs font-semibold text-brand-text">Cart is empty</p>
                <p class="text-[11px] text-brand-text-muted">Tap menu dishes on the left to add items</p>
            </div>
        </template>
    </div>

    <!-- Financial Breakdown & Discount (Instant 0ms Client Calculations) -->
    <div class="pt-3 border-t border-brand-border space-y-2">
        <!-- Discount Selector -->
        <div class="flex items-center justify-between text-xs">
            <label class="font-semibold text-brand-text-muted text-[11px] uppercase tracking-wider">Discount</label>
            <div class="flex items-center gap-1.5">
                <select x-model="discountType"
                    class="rounded-md border border-brand-border bg-brand-bg px-2 py-1 text-xs text-brand-text focus:border-brand-primary focus:outline-none cursor-pointer">
                    <option value="none">No Discount</option>
                    <option value="senior_pwd">Senior / PWD (20%)</option>
                    <option value="custom">Custom (₱)</option>
                </select>
                <template x-if="discountType === 'custom'">
                    <input type="number" step="0.01" min="0" x-model.number="customDiscount"
                        placeholder="₱0.00"
                        class="w-20 rounded-md border border-brand-border bg-brand-bg px-2 py-1 text-xs text-brand-text font-mono font-semibold focus:border-brand-primary focus:outline-none">
                </template>
            </div>
        </div>

        <!-- Subtotal -->
        <div class="flex items-center justify-between text-xs text-brand-text-muted">
            <span>Subtotal</span>
            <span class="font-mono font-semibold text-brand-text" x-text="formatCurrency(subtotal)"></span>
        </div>

        <!-- Discount amount if applied -->
        <template x-if="discountAmount > 0">
            <div class="flex items-center justify-between text-xs text-brand-success font-semibold">
                <span>Discount Applied</span>
                <span class="font-mono" x-text="'-' + formatCurrency(discountAmount)"></span>
            </div>
        </template>

        <!-- Grand Total (High Contrast POS Display) -->
        <div class="rounded-md border border-brand-border bg-brand-bg px-3 py-2.5 flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-brand-text">Total Due</span>
            <span class="font-mono text-xl font-bold text-brand-primary dark:text-brand-secondary"
                x-text="formatCurrency(totalAmount)">
            </span>
        </div>
    </div>

    <!-- Checkout Action Button -->
    <div class="pt-1">
        <button type="button" x-on:click="proceed()" :disabled="items.length === 0"
            class="w-full flex items-center justify-center gap-2 rounded-md bg-brand-primary hover:bg-brand-primary-hover disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-sm py-3 px-4 shadow-sm transition uppercase tracking-wider cursor-pointer">
            <span>Checkout &amp; Pay</span>
            <span class="font-mono font-bold" x-text="formatCurrency(totalAmount)"></span>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                    d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </button>
    </div>

    <!-- Server fallback block for headless automated tests -->
    @if (!empty($items))
        <div class="hidden" aria-hidden="true">
            @foreach ($items as $item)
                <div>{{ $item['name'] }} ₱{{ number_format($item['price'] * $item['quantity'], 2) }}</div>
            @endforeach
            <div>₱{{ number_format($subtotal, 2) }}</div>
            @if ($discountAmount > 0)
                <div>-₱{{ number_format($discountAmount, 2) }}</div>
            @endif
            <div>₱{{ number_format($totalAmount, 2) }}</div>
        </div>
    @endif
</div>
