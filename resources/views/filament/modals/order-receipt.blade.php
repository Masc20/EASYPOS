<div class="mx-auto max-w-sm rounded-lg border border-gray-200 bg-white p-5 font-mono text-xs shadow-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-200">
    <!-- Store Header -->
    <div class="text-center pb-3 border-b border-dashed border-gray-300 dark:border-gray-700">
        <h2 class="text-sm font-black uppercase tracking-wider text-gray-900 dark:text-white">
            {{ $order->branch?->name ?? 'EASYPOS MAIN STORE' }}
        </h2>
        @if ($order->branch?->address)
            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $order->branch->address }}</p>
        @endif
        <div class="mt-2 text-[11px] font-bold text-gray-700 dark:text-gray-300">
            OFFICIAL SALES RECEIPT
        </div>
    </div>

    <!-- Metadata -->
    <div class="py-2.5 space-y-1 text-[11px] border-b border-dashed border-gray-300 dark:border-gray-700">
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Order #:</span>
            <span class="font-bold text-gray-900 dark:text-white">{{ $order->order_number }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Date/Time:</span>
            <span>{{ $order->created_at?->format('M d, Y h:i A') }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Cashier:</span>
            <span>{{ $order->cashier?->name ?? 'Staff' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Dining Mode:</span>
            <span class="font-semibold uppercase">{{ str_replace('_', ' ', $order->order_type) }} {{ $order->table_number ? '(Table ' . $order->table_number . ')' : '' }}</span>
        </div>
    </div>

    <!-- Itemized List -->
    <div class="py-3 border-b border-dashed border-gray-300 dark:border-gray-700">
        <div class="flex justify-between font-bold text-[10px] text-gray-500 dark:text-gray-400 uppercase mb-2">
            <span>Item & Qty</span>
            <span class="text-right">Price</span>
            <span class="text-right">Total</span>
        </div>
        <div class="space-y-1.5">
            @foreach ($order->items as $item)
                <div class="flex justify-between items-start text-[11px]">
                    <div class="flex-1 pr-2">
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $item->meal_name ?? $item->meal?->name ?? 'Dish' }}</div>
                        <div class="text-[10px] text-gray-400">{{ $item->quantity }}x @ ₱{{ number_format((float) $item->unit_price, 2) }}</div>
                    </div>
                    <div class="text-right font-bold text-gray-900 dark:text-white">
                        ₱{{ number_format((float) $item->subtotal, 2) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Totals -->
    <div class="py-2.5 space-y-1 text-[11px] border-b border-dashed border-gray-300 dark:border-gray-700">
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Subtotal:</span>
            <span>₱{{ number_format((float) $order->subtotal, 2) }}</span>
        </div>
        @if ((float) $order->discount_amount > 0)
            <div class="flex justify-between text-amber-600 dark:text-amber-400 font-semibold">
                <span>Discount Applied:</span>
                <span>-₱{{ number_format((float) $order->discount_amount, 2) }}</span>
            </div>
        @endif
        <div class="flex justify-between text-sm font-black text-gray-900 dark:text-white pt-1">
            <span>TOTAL AMOUNT:</span>
            <span>₱{{ number_format((float) $order->total_amount, 2) }}</span>
        </div>
    </div>

    <!-- Payments -->
    <div class="pt-2.5 space-y-1 text-[11px]">
        @foreach ($order->payments as $payment)
            <div class="flex justify-between text-gray-700 dark:text-gray-300">
                <span class="capitalize">{{ str_replace('_', ' ', $payment->payment_method) }} Paid:</span>
                <span>₱{{ number_format((float) $payment->amount, 2) }}</span>
            </div>
            @if ($payment->amount_tendered)
                <div class="flex justify-between text-gray-500">
                    <span>Amount Tendered:</span>
                    <span>₱{{ number_format((float) $payment->amount_tendered, 2) }}</span>
                </div>
            @endif
            @if ($payment->change_given)
                <div class="flex justify-between text-gray-500 font-bold">
                    <span>Change:</span>
                    <span>₱{{ number_format((float) $payment->change_given, 2) }}</span>
                </div>
            @endif
        @endforeach
    </div>

    <!-- Footer Message -->
    <div class="mt-4 pt-3 text-center text-[10px] text-gray-400 dark:text-gray-500 border-t border-dashed border-gray-300 dark:border-gray-700">
        <p>Thank you for dining with us!</p>
        <p class="mt-0.5">Please retain this receipt for your records.</p>
    </div>
</div>
