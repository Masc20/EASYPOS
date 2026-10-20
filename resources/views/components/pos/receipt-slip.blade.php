@props(['order', 'showActions' => true])

@if ($order)
    <div class="receipt-slip-container">
        <!-- Scoped Print and Visual Receipt Styles -->
        <style>
            .receipt-paper-wrapper {
                background-color: #ffffff !important;
                color: #18181b !important;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
                font-size: 11px !important;
                line-height: 1.35 !important;
                padding: 20px !important;
                border-radius: 8px !important;
                border: 1px solid #e4e4e7 !important;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06) !important;
                max-width: 380px !important;
                margin: 0 auto !important;
                text-align: left !important;
            }
            .receipt-paper-wrapper * {
                box-sizing: border-box !important;
            }
            .receipt-divider {
                border-bottom: 1px dashed #d4d4d8 !important;
                margin: 10px 0 !important;
                height: 0 !important;
            }
            .receipt-solid-divider {
                border-bottom: 1px solid #e4e4e7 !important;
                margin: 6px 0 !important;
                height: 0 !important;
            }
            .receipt-row {
                display: flex !important;
                justify-content: space-between !important;
                align-items: flex-start !important;
                margin-bottom: 3px !important;
            }
            .receipt-muted {
                color: #71717a !important;
            }
            .receipt-dark {
                color: #18181b !important;
            }
            .receipt-bold {
                font-weight: 700 !important;
            }
            .receipt-black {
                font-weight: 900 !important;
            }
            .receipt-header-title {
                font-size: 15px !important;
                font-weight: 900 !important;
                letter-spacing: -0.025em !important;
                text-transform: uppercase !important;
                text-align: center !important;
                margin: 0 !important;
                color: #18181b !important;
            }
            .receipt-header-subtitle {
                font-size: 11px !important;
                font-weight: 600 !important;
                color: #3f3f46 !important;
                text-align: center !important;
                margin: 2px 0 0 0 !important;
            }
            .receipt-header-meta {
                font-size: 10px !important;
                color: #71717a !important;
                text-align: center !important;
                margin: 1px 0 0 0 !important;
            }
            .receipt-badge-ticket {
                background-color: #fffbeb !important;
                color: #b45309 !important;
                border: 1px solid #fde68a !important;
                padding: 1px 5px !important;
                border-radius: 3px !important;
                font-weight: 700 !important;
                font-size: 10px !important;
            }
            .receipt-sig-line {
                border-bottom: 1px solid #71717a !important;
                width: 140px !important;
                display: inline-block !important;
                height: 12px !important;
            }

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
        <div id="printable-receipt-{{ $order->id }}" class="receipt-paper-wrapper">

            <!-- Store & Tax Registration Header -->
            <div style="text-align: center; padding-bottom: 8px;">
                <h2 class="receipt-header-title">{{ config('app.name', 'EASYPOS') }}</h2>
                <p class="receipt-header-subtitle">{{ $order->branch?->name ?? 'Downtown Flagship' }}</p>
                @if ($order->branch?->address)
                    <p class="receipt-header-meta">{{ $order->branch->address }}</p>
                @endif
                @if ($order->branch?->phone)
                    <p class="receipt-header-meta">Tel: {{ $order->branch->phone }}</p>
                @endif
                <div style="padding-top: 3px;" class="receipt-header-meta">
                    <p>VAT REG TIN: 009-876-543-000</p>
                    <p>MIN: MIN-{{ $order->branch?->code ?? 'B01' }}-POS01</p>
                    <p>SERIAL NO: POS-SN-{{ str_pad($order->branch?->id ?? 1, 4, '0', STR_PAD_LEFT) }}-2026</p>
                </div>
            </div>

            <div class="receipt-divider"></div>

            <!-- Order Metadata -->
            <div style="padding: 2px 0;">
                <div class="receipt-row">
                    <span class="receipt-muted">Invoice #:</span>
                    <span class="receipt-bold receipt-dark">{{ $order->order_number }}</span>
                </div>
                @if ($order->kitchenTicket)
                    <div class="receipt-row" style="align-items: center !important;">
                        <span class="receipt-muted">Kitchen Ticket:</span>
                        <span class="receipt-badge-ticket">
                            {{ $order->kitchenTicket->ticket_number }}
                        </span>
                    </div>
                @endif
                <div class="receipt-row">
                    <span class="receipt-muted">Date/Time:</span>
                    <span class="receipt-dark">{{ $order->created_at?->format('M d, Y h:i A') }}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-muted">Cashier:</span>
                    <span class="receipt-dark">{{ $order->cashier?->name ?? 'Staff' }} ({{ $order->cashier?->emp_id ?? 'CSH' }})</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-muted">Dining Mode:</span>
                    <span class="receipt-bold receipt-dark" style="text-transform: uppercase;">
                        {{ $order->order_type_label }}
                        @if ($order->table_number)
                            • Table {{ $order->table_number }}
                        @endif
                    </span>
                </div>
                @if ($order->customer_name)
                    <div class="receipt-row">
                        <span class="receipt-muted">Customer:</span>
                        <span class="receipt-dark">{{ $order->customer_name }}</span>
                    </div>
                @endif
            </div>

            <div class="receipt-divider"></div>

            <!-- Line Items Table -->
            <div style="padding: 2px 0;">
                <div class="receipt-row receipt-muted" style="font-size: 10px; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">
                    <span>Item & Qty</span>
                    <span>Total</span>
                </div>

                @foreach ($order->items as $item)
                    <div style="margin-bottom: 6px;">
                        <div class="receipt-row">
                            <span class="receipt-bold receipt-dark" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $item->meal_name ?: ($item->meal?->name ?: 'Menu Item') }}
                            </span>
                            <span class="receipt-bold receipt-dark">₱{{ number_format((float) $item->subtotal, 2) }}</span>
                        </div>
                        <div class="receipt-row" style="font-size: 10px; padding-left: 6px;">
                            <span class="receipt-muted">{{ $item->quantity }} x ₱{{ number_format((float) $item->unit_price, 2) }}</span>
                            @if ($item->special_instructions)
                                <span class="receipt-muted" style="font-style: italic;">({{ $item->special_instructions }})</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="receipt-divider"></div>

            <!-- Financial Summary -->
            <div style="padding: 2px 0;">
                <div class="receipt-row">
                    <span class="receipt-muted">Subtotal (Gross):</span>
                    <span class="receipt-dark">₱{{ number_format((float) $order->subtotal, 2) }}</span>
                </div>
                @if ((float) $order->discount_amount > 0)
                    <div class="receipt-row" style="color: #047857; font-weight: 700;">
                        <span>Discount Applied:</span>
                        <span>-₱{{ number_format((float) $order->discount_amount, 2) }}</span>
                    </div>
                @endif
                <div class="receipt-solid-divider"></div>
                <div class="receipt-row" style="font-size: 13px; font-weight: 900; padding-top: 2px;">
                    <span>TOTAL AMOUNT:</span>
                    <span>₱{{ number_format((float) $order->total_amount, 2) }}</span>
                </div>
            </div>

            <!-- Payment Breakdown -->
            @php
                $payment = $order->payments->first();
            @endphp
            @if ($payment)
                <div class="receipt-divider"></div>
                <div style="padding: 2px 0;">
                    <div class="receipt-row">
                        <span class="receipt-muted">Payment Tender:</span>
                        <span class="receipt-bold receipt-dark" style="text-transform: capitalize;">{{ str_replace('_', ' ', $payment->payment_method) }}</span>
                    </div>
                    @if ($payment->payment_method === 'cash')
                        <div class="receipt-row">
                            <span class="receipt-muted">Amount Tendered:</span>
                            <span class="receipt-dark">₱{{ number_format((float) ($payment->amount_tendered ?? $payment->amount), 2) }}</span>
                        </div>
                        <div class="receipt-row receipt-bold">
                            <span class="receipt-muted">Change Given:</span>
                            <span class="receipt-dark">₱{{ number_format((float) ($payment->change_given ?? 0), 2) }}</span>
                        </div>
                    @elseif ($payment->reference_number)
                        <div class="receipt-row">
                            <span class="receipt-muted">Trace / Ref #:</span>
                            <span class="receipt-dark" style="font-family: inherit;">{{ $payment->reference_number }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <div class="receipt-divider"></div>

            <!-- Tax Breakdown (Official BIR Compliance) -->
            <div style="font-size: 10px; color: #52525b; padding: 2px 0;">
                <div style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #3f3f46; margin-bottom: 4px;">
                    Tax Breakdown (12% VAT):
                </div>
                <div class="receipt-row">
                    <span>VATable Sales (12%):</span>
                    <span class="receipt-dark">₱{{ number_format((float) $order->vatable_sales, 2) }}</span>
                </div>
                <div class="receipt-row">
                    <span>VAT Amount (12%):</span>
                    <span class="receipt-dark">₱{{ number_format((float) $order->vat_amount, 2) }}</span>
                </div>
                <div class="receipt-row">
                    <span>VAT-Exempt Sales:</span>
                    <span class="receipt-dark">₱{{ number_format((float) $order->vat_exempt_sales, 2) }}</span>
                </div>
                <div class="receipt-row">
                    <span>Zero-Rated Sales:</span>
                    <span class="receipt-dark">₱{{ number_format((float) $order->zero_rated_sales, 2) }}</span>
                </div>
            </div>

            <!-- Senior / PWD Signature (Required by BIR when discount is applied) -->
            @if ((float) $order->discount_amount > 0)
                <div class="receipt-divider"></div>
                <div style="font-size: 10px; color: #52525b; padding: 2px 0;">
                    <div style="font-weight: 700; text-transform: uppercase; color: #3f3f46; margin-bottom: 4px;">
                        Senior / PWD Signature Line:
                    </div>
                    <div style="padding-top: 4px;">
                        <div class="receipt-row" style="align-items: center !important;">
                            <span>Customer Name:</span>
                            <span class="receipt-sig-line"></span>
                        </div>
                        <div class="receipt-row" style="align-items: center !important; margin-top: 4px;">
                            <span>Senior / PWD ID:</span>
                            <span class="receipt-sig-line"></span>
                        </div>
                        <div class="receipt-row" style="align-items: center !important; margin-top: 4px;">
                            <span>Signature:</span>
                            <span class="receipt-sig-line"></span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="receipt-divider"></div>

            <!-- Footer Compliance Notice -->
            <div style="text-align: center; font-size: 10px; color: #71717a; padding-top: 4px;">
                <p style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #3f3f46; margin: 0 0 2px 0;">
                    Official Sales Receipt
                </p>
                <p style="margin: 0;">Thank you for dining with us! Please come again.</p>
                <p style="font-size: 9px; color: #a1a1aa; margin: 4px 0 0 0;">System Generated • EASYPOS v1.0</p>
            </div>
        </div>

        @if ($showActions)
            <!-- Print Action Trigger Button -->
            <div style="margin-top: 14px; text-align: center;">
                <button type="button"
                    onclick="window.print()"
                    style="display: inline-flex; align-items: center; gap: 8px; background-color: #18181b; color: #ffffff; font-weight: 700; font-size: 12px; padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Print Thermal Receipt (80mm)</span>
                </button>
            </div>
        @endif
    </div>
@endif
