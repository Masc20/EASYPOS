@props(['order', 'showActions' => true])

@if ($order)
    <div class="receipt-slip-container">
        <!-- Physical Thermal Printer Hardware Print Rules (80mm Paper Roll) -->
        <style>
            @media print {
                body * {
                    visibility: hidden !important;
                }
                #printable-receipt-{{ $order->id }},
                #printable-receipt-{{ $order->id }} * {
                    visibility: visible !important;
                }
                #printable-receipt-{{ $order->id }} {
                    position: fixed !important;
                    left: 0 !important;
                    top: 0 !important;
                    width: 78mm !important;
                    max-width: 100% !important;
                    margin: 0 !important;
                    padding: 6px !important;
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
        <div id="printable-receipt-{{ $order->id }}" class="bg-white text-zinc-900 font-mono text-[11px] leading-snug p-5 rounded-lg border border-zinc-200 shadow-md max-w-[380px] mx-auto text-left box-border">

            <!-- Store & Tax Registration Header -->
            <div class="text-center pb-2">
                <h2 class="text-[15px] font-black tracking-tight uppercase text-zinc-900 m-0">{{ config('app.name', 'EASYPOS') }}</h2>
                <p class="text-[11px] font-semibold text-zinc-700 text-center mt-0.5 mb-0">{{ $order->branch?->name ?? 'Downtown Flagship' }}</p>
                @if ($order->branch?->address)
                    <p class="text-[10px] text-zinc-500 text-center mt-px mb-0">{{ $order->branch->address }}</p>
                @endif
                @if ($order->branch?->phone)
                    <p class="text-[10px] text-zinc-500 text-center mt-px mb-0">Tel: {{ $order->branch->phone }}</p>
                @endif
                <div class="text-[10px] text-zinc-500 text-center mt-px mb-0 pt-1">
                    <p class="m-0">VAT REG TIN: 009-876-543-000</p>
                    <p class="m-0">MIN: MIN-{{ $order->branch?->code ?? 'B01' }}-POS01</p>
                    <p class="m-0">SERIAL NO: POS-SN-{{ str_pad($order->branch?->id ?? 1, 4, '0', STR_PAD_LEFT) }}-2026</p>
                </div>
            </div>

            <div class="border-b border-dashed border-zinc-300 my-2.5 h-0"></div>

            <!-- Order Metadata -->
            <div class="py-0.5">
                <div class="flex justify-between items-start mb-0.5">
                    <span class="text-zinc-500">Invoice #:</span>
                    <span class="font-bold text-zinc-900">{{ $order->order_number }}</span>
                </div>
                @if ($order->kitchenTicket)
                    <div class="flex justify-between items-center mb-0.5">
                        <span class="text-zinc-500">Kitchen Ticket:</span>
                        <span class="bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded text-[10px] font-bold">
                            {{ $order->kitchenTicket->ticket_number }}
                        </span>
                    </div>
                @endif
                <div class="flex justify-between items-start mb-0.5">
                    <span class="text-zinc-500">Date/Time:</span>
                    <span class="text-zinc-900">{{ $order->created_at?->format('M d, Y h:i A') }}</span>
                </div>
                <div class="flex justify-between items-start mb-0.5">
                    <span class="text-zinc-500">Cashier:</span>
                    <span class="text-zinc-900">{{ $order->cashier?->name ?? 'Staff' }} ({{ $order->cashier?->emp_id ?? 'CSH' }})</span>
                </div>
                <div class="flex justify-between items-start mb-0.5">
                    <span class="text-zinc-500">Dining Mode:</span>
                    <span class="font-bold text-zinc-900 uppercase">
                        {{ $order->order_type_label }}
                        @if ($order->table_number)
                            • Table {{ $order->table_number }}
                        @endif
                    </span>
                </div>
                @if ($order->customer_name)
                    <div class="flex justify-between items-start mb-0.5">
                        <span class="text-zinc-500">Customer:</span>
                        <span class="text-zinc-900">{{ $order->customer_name }}</span>
                    </div>
                @endif
            </div>

            <div class="border-b border-dashed border-zinc-300 my-2.5 h-0"></div>

            <!-- Line Items Table -->
            <div class="py-0.5">
                <div class="flex justify-between items-start mb-1.5 text-zinc-500 text-[10px] font-bold uppercase">
                    <span>Item & Qty</span>
                    <span>Total</span>
                </div>

                @foreach ($order->items as $item)
                    <div class="mb-1.5">
                        <div class="flex justify-between items-start mb-0.5">
                            <span class="font-bold text-zinc-900 max-w-[250px] truncate">
                                {{ $item->meal_name ?: ($item->meal?->name ?: 'Menu Item') }}
                            </span>
                            <span class="font-bold text-zinc-900">₱{{ number_format((float) $item->subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-start text-[10px] pl-1.5">
                            <span class="text-zinc-500">{{ $item->quantity }} x ₱{{ number_format((float) $item->unit_price, 2) }}</span>
                            @if ($item->special_instructions)
                                <span class="text-zinc-500 italic">({{ $item->special_instructions }})</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-b border-dashed border-zinc-300 my-2.5 h-0"></div>

            <!-- Financial Summary -->
            <div class="py-0.5">
                <div class="flex justify-between items-start mb-0.5">
                    <span class="text-zinc-500">Subtotal (Gross):</span>
                    <span class="text-zinc-900">₱{{ number_format((float) $order->subtotal, 2) }}</span>
                </div>
                @if ((float) $order->discount_amount > 0)
                    <div class="flex justify-between items-start mb-0.5 text-emerald-700 font-bold">
                        <span>Discount Applied:</span>
                        <span>-₱{{ number_format((float) $order->discount_amount, 2) }}</span>
                    </div>
                @endif
                <div class="border-b border-zinc-200 my-1.5 h-0"></div>
                <div class="flex justify-between items-start text-sm font-black pt-0.5">
                    <span>TOTAL AMOUNT:</span>
                    <span>₱{{ number_format((float) $order->total_amount, 2) }}</span>
                </div>
            </div>

            <!-- Payment Breakdown -->
            @php
                $payment = $order->payments->first();
            @endphp
            @if ($payment)
                <div class="border-b border-dashed border-zinc-300 my-2.5 h-0"></div>
                <div class="py-0.5">
                    <div class="flex justify-between items-start mb-0.5">
                        <span class="text-zinc-500">Payment Tender:</span>
                        <span class="font-bold text-zinc-900 capitalize">{{ str_replace('_', ' ', $payment->payment_method) }}</span>
                    </div>
                    @if ($payment->payment_method === 'cash')
                        <div class="flex justify-between items-start mb-0.5">
                            <span class="text-zinc-500">Amount Tendered:</span>
                            <span class="text-zinc-900">₱{{ number_format((float) ($payment->amount_tendered ?? $payment->amount), 2) }}</span>
                        </div>
                        <div class="flex justify-between items-start mb-0.5 font-bold">
                            <span class="text-zinc-500">Change Given:</span>
                            <span class="text-zinc-900">₱{{ number_format((float) ($payment->change_given ?? 0), 2) }}</span>
                        </div>
                    @elseif ($payment->reference_number)
                        <div class="flex justify-between items-start mb-0.5">
                            <span class="text-zinc-500">Trace / Ref #:</span>
                            <span class="text-zinc-900 font-mono">{{ $payment->reference_number }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <div class="border-b border-dashed border-zinc-300 my-2.5 h-0"></div>

            <!-- Tax Breakdown (Official BIR Compliance) -->
            <div class="text-[10px] text-zinc-600 py-0.5">
                <div class="font-bold uppercase tracking-wider text-zinc-700 mb-1">
                    Tax Breakdown (12% VAT):
                </div>
                <div class="flex justify-between items-start mb-0.5">
                    <span>VATable Sales (12%):</span>
                    <span class="text-zinc-900">₱{{ number_format((float) $order->vatable_sales, 2) }}</span>
                </div>
                <div class="flex justify-between items-start mb-0.5">
                    <span>VAT Amount (12%):</span>
                    <span class="text-zinc-900">₱{{ number_format((float) $order->vat_amount, 2) }}</span>
                </div>
                <div class="flex justify-between items-start mb-0.5">
                    <span>VAT-Exempt Sales:</span>
                    <span class="text-zinc-900">₱{{ number_format((float) $order->vat_exempt_sales, 2) }}</span>
                </div>
                <div class="flex justify-between items-start mb-0.5">
                    <span>Zero-Rated Sales:</span>
                    <span class="text-zinc-900">₱{{ number_format((float) $order->zero_rated_sales, 2) }}</span>
                </div>
            </div>

            <!-- Senior / PWD Signature (Required by BIR when discount is applied) -->
            @if ((float) $order->discount_amount > 0)
                <div class="border-b border-dashed border-zinc-300 my-2.5 h-0"></div>
                <div class="text-[10px] text-zinc-600 py-0.5">
                    <div class="font-bold uppercase text-zinc-700 mb-1">
                        Senior / PWD Signature Line:
                    </div>
                    <div class="pt-1">
                        <div class="flex justify-between items-center mb-1">
                            <span>Customer Name:</span>
                            <span class="border-b border-zinc-500 w-36 inline-block h-3"></span>
                        </div>
                        <div class="flex justify-between items-center mb-1">
                            <span>Senior / PWD ID:</span>
                            <span class="border-b border-zinc-500 w-36 inline-block h-3"></span>
                        </div>
                        <div class="flex justify-between items-center mb-1">
                            <span>Signature:</span>
                            <span class="border-b border-zinc-500 w-36 inline-block h-3"></span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="border-b border-dashed border-zinc-300 my-2.5 h-0"></div>

            <!-- Footer Compliance Notice -->
            <div class="text-center text-[10px] text-zinc-500 pt-1">
                <p class="font-bold uppercase tracking-wider text-zinc-700 mb-0.5">
                    Official Sales Receipt
                </p>
                <p class="m-0">Thank you for dining with us! Please come again.</p>
                <p class="text-[9px] text-zinc-400 mt-1 mb-0">System Generated • EASYPOS v1.0</p>
            </div>
        </div>

        @if ($showActions)
            <!-- Print Action Trigger Button -->
            <div class="mt-3.5 text-center">
                <button type="button"
                    onclick="window.print()"
                    class="inline-flex items-center gap-2 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs px-4.5 py-2 rounded-lg cursor-pointer shadow-xs transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Print Thermal Receipt (80mm)</span>
                </button>
            </div>
        @endif
    </div>
@endif
