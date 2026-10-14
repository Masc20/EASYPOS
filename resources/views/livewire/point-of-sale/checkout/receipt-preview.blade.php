<x-ui.modal name="receipt-modal" title="Official Sales Receipt" maxWidth="sm">
    @if ($order)
        <!-- Scoped Print Styles (Isolates printout to ONLY the receipt slip) -->
        <style>
            @media print {
                /* Hide everything on the page */
                body * {
                    visibility: hidden !important;
                }

                /* Make only the receipt and its descendants visible */
                #printable-receipt,
                #printable-receipt * {
                    visibility: visible !important;
                }

                /* Position receipt at top-left of page with 80mm thermal roll sizing */
                #printable-receipt {
                    position: fixed !important;
                    left: 0 !important;
                    top: 0 !important;
                    width: 78mm !important;
                    max-width: 100% !important;
                    margin: 0 !important;
                    padding: 8px !important;
                    border: none !important;
                    box-shadow: none !important;
                    background: white !important;
                    color: black !important;
                    font-size: 11px !important;
                    line-height: 1.3 !important;
                }

                @page {
                    margin: 0;
                    size: 80mm auto;
                }
            }
        </style>

        <!-- Receipt Paper Frame -->
        <div id="printable-receipt"
            class="bg-white text-zinc-900 p-5 rounded-md border border-zinc-200 font-mono text-xs shadow-inner">
            
            <!-- Store & Tax Registration Header -->
            <div class="text-center space-y-0.5 pb-3 border-b border-dashed border-zinc-300">
                <h2 class="text-base font-black tracking-tight uppercase">{{ config('app.name', 'EASYPOS') }}</h2>
                <p class="text-[11px] font-semibold text-zinc-700">{{ $order->branch->name ?? 'Downtown Flagship' }}</p>
                @if ($order->branch?->address)
                    <p class="text-[10px] text-zinc-500">{{ $order->branch->address }}</p>
                @endif
                @if ($order->branch?->phone)
                    <p class="text-[10px] text-zinc-500">Tel: {{ $order->branch->phone }}</p>
                @endif
                <div class="pt-1 text-[10px] text-zinc-500 space-y-0.5">
                    <p>VAT REG TIN: 009-876-543-000</p>
                    <p>MIN: MIN-{{ $order->branch?->code ?? 'B01' }}-POS01</p>
                </div>
            </div>

            <!-- Order Metadata -->
            <div class="py-2.5 text-[11px] space-y-1 border-b border-dashed border-zinc-300">
                <div class="flex justify-between">
                    <span class="text-zinc-500">Invoice #:</span>
                    <span class="font-bold">{{ $order->order_number }}</span>
                </div>
                @if ($order->kitchenTicket)
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Kitchen Ticket:</span>
                        <span class="font-bold text-amber-700 bg-amber-50 px-1 rounded-sm border border-amber-200">
                            {{ $order->kitchenTicket->ticket_number }}
                        </span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-zinc-500">Date/Time:</span>
                    <span>{{ $order->created_at->format('M d, Y h:i A') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">Cashier:</span>
                    <span>{{ $order->cashier->name ?? 'Staff' }} ({{ $order->cashier->emp_id ?? 'CSH' }})</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">Dining Type:</span>
                    <span class="font-bold uppercase">
                        {{ $order->order_type_label }}
                        @if ($order->table_number)
                            • Table {{ $order->table_number }}
                        @endif
                    </span>
                </div>
                @if ($order->customer_name)
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Customer:</span>
                        <span>{{ $order->customer_name }}</span>
                    </div>
                @endif
            </div>

            <!-- Line Items Table -->
            <div class="py-3 border-b border-dashed border-zinc-300 space-y-2">
                @foreach ($order->items as $item)
                    <div>
                        <div class="flex justify-between font-semibold">
                            <span class="line-clamp-1">{{ $item->meal_name }}</span>
                            <span>₱{{ number_format($item->subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-[10px] text-zinc-500 pl-2">
                            <span>{{ $item->quantity }} x ₱{{ number_format($item->unit_price, 2) }}</span>
                            @if ($item->special_instructions)
                                <span class="italic text-zinc-600">({{ $item->special_instructions }})</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Financial Summary -->
            <div class="py-2.5 text-[11px] space-y-1 border-b border-dashed border-zinc-300">
                <div class="flex justify-between">
                    <span class="text-zinc-500">Subtotal (Gross):</span>
                    <span>₱{{ number_format($order->subtotal, 2) }}</span>
                </div>
                @if ($order->discount_amount > 0)
                    <div class="flex justify-between text-emerald-700 font-semibold">
                        <span>Discount Applied:</span>
                        <span>-₱{{ number_format($order->discount_amount, 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-sm font-black pt-1 border-t border-zinc-200">
                    <span>TOTAL DUE:</span>
                    <span>₱{{ number_format($order->total_amount, 2) }}</span>
                </div>
            </div>

            <!-- Payment Breakdown -->
            @php
                $payment = $order->payments->first();
            @endphp
            @if ($payment)
                <div class="py-2.5 text-[11px] space-y-1 border-b border-dashed border-zinc-300">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Payment Method:</span>
                        <span class="font-bold">{{ $payment->method_label }}</span>
                    </div>
                    @if ($payment->payment_method === 'cash')
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Amount Tendered:</span>
                            <span>₱{{ number_format($payment->amount_tendered, 2) }}</span>
                        </div>
                        <div class="flex justify-between font-bold">
                            <span class="text-zinc-500">Change:</span>
                            <span>₱{{ number_format($payment->change_given, 2) }}</span>
                        </div>
                    @elseif ($payment->reference_number)
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Trace / Ref #:</span>
                            <span class="font-mono">{{ $payment->reference_number }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Tax Breakdown (Official BIR Compliance) -->
            <div class="py-2.5 text-[10px] space-y-1 border-b border-dashed border-zinc-300 text-zinc-600">
                <div class="font-bold uppercase tracking-wider text-zinc-700 pb-0.5">Tax Summary:</div>
                <div class="flex justify-between">
                    <span>VATable Sales (12%):</span>
                    <span class="font-mono">₱{{ number_format($order->vatable_sales, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>VAT Amount (12%):</span>
                    <span class="font-mono">₱{{ number_format($order->vat_amount, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>VAT-Exempt Sales:</span>
                    <span class="font-mono">₱{{ number_format($order->vat_exempt_sales, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Zero-Rated Sales:</span>
                    <span class="font-mono">₱{{ number_format($order->zero_rated_sales, 2) }}</span>
                </div>
            </div>

            <!-- Senior / PWD Signature (Required by BIR when discount is applied) -->
            @if ($order->discount_amount > 0)
                <div class="py-2.5 text-[10px] space-y-2 border-b border-dashed border-zinc-300 text-zinc-600">
                    <div class="font-bold uppercase tracking-wider text-zinc-700">Senior / PWD Details:</div>
                    <div class="space-y-1.5 pt-1">
                        <div class="flex items-center justify-between">
                            <span>Name:</span>
                            <span class="border-b border-zinc-400 w-36 inline-block"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>ID No:</span>
                            <span class="border-b border-zinc-400 w-36 inline-block"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Signature:</span>
                            <span class="border-b border-zinc-400 w-36 inline-block"></span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Footer Compliance Notice -->
            <div class="pt-4 text-center space-y-1 text-[10px] text-zinc-500">
                <p class="font-bold text-zinc-700 uppercase tracking-wider">Official Sales Invoice</p>
                <p>Thank you for dining with us! Please come again.</p>
                <p class="text-[9px] text-zinc-400 pt-1">System Generated • EASYPOS v1.0</p>
            </div>
        </div>

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
