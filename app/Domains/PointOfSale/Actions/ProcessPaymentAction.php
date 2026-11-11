<?php

namespace App\Domains\PointOfSale\Actions;

use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\PointOfSale\Events\KitchenTicketDispatched;
use App\Domains\PointOfSale\Events\PaymentProcessed;
use App\Domains\PointOfSale\Models\BranchDailySequence;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
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
        if ($order->payment_status === PaymentStatus::PAID->value) {
            throw new InvalidArgumentException("Order #{$order->order_number} has already been paid.");
        }

        if ($order->status === OrderStatus::CANCELLED->value) {
            throw new InvalidArgumentException("Order #{$order->order_number} has been cancelled.");
        }

        return DB::transaction(function () use ($order, $data) {
            // Acquire pessimistic row lock on order to eliminate race conditions
            $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status === PaymentStatus::PAID->value) {
                throw new InvalidArgumentException("Order #{$order->order_number} has already been paid.");
            }

            if ($order->status === OrderStatus::CANCELLED->value) {
                throw new InvalidArgumentException("Order #{$order->order_number} has been cancelled.");
            }

            if (Payment::where('order_id', $order->id)->where('status', 'successful')->exists()) {
                throw new InvalidArgumentException("Payment for Order #{$order->order_number} already exists.");
            }

            $paymentMethod = $data['payment_method'] ?? PaymentMethod::CASH->value;
            if ($paymentMethod instanceof PaymentMethod) {
                $paymentMethod = $paymentMethod->value;
            }
            $validMethods = array_map(fn (PaymentMethod $case) => $case->value, PaymentMethod::cases());
            if (! in_array($paymentMethod, $validMethods, true)) {
                throw new InvalidArgumentException("Invalid payment method: {$paymentMethod}");
            }

            $referenceNumber = $data['reference_number'] ?? null;
            if ($referenceNumber !== null && mb_strlen((string) $referenceNumber) > 100) {
                throw new InvalidArgumentException("Reference number cannot exceed 100 characters.");
            }

            $totalDue = (float) $order->total_amount;
            $cashierId = $data['cashier_id'] ?? $order->cashier_id;

            $amountTendered = isset($data['amount_tendered']) ? (float) $data['amount_tendered'] : $totalDue;
            $changeGiven = 0.00;

            if ($paymentMethod === PaymentMethod::CASH->value) {
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
                'payment_status' => PaymentStatus::PAID->value,
                'status' => OrderStatus::IN_KITCHEN->value,
            ]);

            // 3. Generate Sequential Kitchen Ticket via atomic BranchDailySequence row-level counter
            $nextTicketSeq = BranchDailySequence::getNextSequence($order->branch_id, 'kitchen_ticket', today()->toDateString());
            $ticketNumber = sprintf('K-%03d', $nextTicketSeq);

            $startOfDay = today()->startOfDay();
            $endOfDay = today()->addDay()->startOfDay();
            while (KitchenTicket::where('branch_id', $order->branch_id)
                ->where('created_at', '>=', $startOfDay)
                ->where('created_at', '<', $endOfDay)
                ->where('ticket_number', $ticketNumber)
                ->exists()) {
                $nextTicketSeq = BranchDailySequence::getNextSequence($order->branch_id, 'kitchen_ticket', today()->toDateString());
                $ticketNumber = sprintf('K-%03d', $nextTicketSeq);
            }

            $kitchenTicket = KitchenTicket::create([
                'ticket_number' => $ticketNumber,
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'status' => KitchenTicketStatus::NEW->value,
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

            DB::afterCommit(function () use ($order, $payment, $kitchenTicket) {
                event(new PaymentProcessed($order, $payment));
                event(new KitchenTicketDispatched($kitchenTicket));
            });

            return [
                'order' => $order,
                'payment' => $payment,
                'kitchen_ticket' => $kitchenTicket,
            ];
        });
    }
}
