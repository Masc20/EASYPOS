<?php

namespace App\Domains\PointOfSale\Actions;

use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\PointOfSale\Events\KitchenTicketDispatched;
use App\Domains\PointOfSale\Events\PaymentProcessed;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcessPaymentAction
{
    /**
     * Process payment for an order and automatically route a ticket to the Kitchen Display System.
     *
     * @param Order $order
     * @param array{
     *     cashier_id: int,
     *     payment_method: string,
     *     amount_tendered?: ?float,
     *     reference_number?: ?string
     * } $data
     * @return array{order: Order, payment: Payment, kitchen_ticket: KitchenTicket}
     */
    public function execute(Order $order, array $data): array
    {
        if ($order->payment_status === 'paid') {
            throw new InvalidArgumentException("Order #{$order->order_number} has already been paid.");
        }

        return DB::transaction(function () use ($order, $data) {
            $totalDue = (float) $order->total_amount;
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $cashierId = $data['cashier_id'];
            $referenceNumber = $data['reference_number'] ?? null;

            $amountTendered = isset($data['amount_tendered']) ? (float) $data['amount_tendered'] : $totalDue;
            $changeGiven = 0.00;

            if ($paymentMethod === 'cash') {
                if ($amountTendered < $totalDue) {
                    throw new InvalidArgumentException(
                        sprintf("Amount tendered (₱%.2f) is less than the total due (₱%.2f).", $amountTendered, $totalDue)
                    );
                }
                $changeGiven = round($amountTendered - $totalDue, 2);
            } else {
                $amountTendered = $totalDue;
                $changeGiven = 0.00;
            }

            // 1. Create Payment Record
            $payment = Payment::create([
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'cashier_id' => $cashierId,
                'payment_method' => $paymentMethod,
                'amount' => $totalDue,
                'amount_tendered' => $amountTendered,
                'change_given' => $changeGiven,
                'reference_number' => $referenceNumber,
                'status' => 'successful',
            ]);

            // 2. Mark Order as Paid and in Kitchen
            $order->update([
                'payment_status' => 'paid',
                'status' => 'in_kitchen',
            ]);

            // 3. Generate Sequential Kitchen Ticket
            $todayTicketCount = KitchenTicket::where('branch_id', $order->branch_id)
                ->whereDate('created_at', today())
                ->lockForUpdate()
                ->count();

            $ticketSeq = str_pad((string) ($todayTicketCount + 1), 3, '0', STR_PAD_LEFT);
            $ticketNumber = "K-{$ticketSeq}";

            $kitchenTicket = KitchenTicket::create([
                'ticket_number' => $ticketNumber,
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'status' => 'new',
                'priority' => 'normal',
            ]);

            // 4. Create Kitchen Ticket Items for Cooks
            $order->loadMissing('items');
            $ticketItemsData = [];
            foreach ($order->items as $item) {
                $ticketItemsData[] = [
                    'ticket_id' => $kitchenTicket->id,
                    'order_item_id' => $item->id,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($ticketItemsData)) {
                KitchenTicketItem::insert($ticketItemsData);
            }

            $kitchenTicket->load('items.orderItem');

            // Dispatch Events
            event(new PaymentProcessed($order, $payment));
            event(new KitchenTicketDispatched($kitchenTicket));

            return [
                'order' => $order,
                'payment' => $payment,
                'kitchen_ticket' => $kitchenTicket,
            ];
        });
    }
}
