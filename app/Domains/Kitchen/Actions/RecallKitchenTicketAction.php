<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Actions\Concerns\AuthorizesKitchenActions;
use App\Domains\Kitchen\Events\KitchenTicketStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;

class RecallKitchenTicketAction
{
    use AuthorizesKitchenActions;

    /**
     * Recall a served ticket back to the ready queue.
     */
    public function execute(KitchenTicket $ticket): KitchenTicket
    {
        $this->authorizeKitchenAction($ticket, 'recall kitchen tickets');

        return DB::transaction(function () use ($ticket) {
            $order = Order::whereKey($ticket->order_id)->lockForUpdate()->firstOrFail();
            $ticket = KitchenTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($order->status === OrderStatus::CANCELLED->value || $ticket->status === KitchenTicketStatus::CANCELLED->value) {
                throw new \InvalidArgumentException('Cancelled orders cannot be recalled.');
            }
            $ticket->setRelation('order', $order);
            $previousStatus = $ticket->status;

            $ticket->update([
                'status' => KitchenTicketStatus::READY->value,
                'served_at' => null,
            ]);

            if ($ticket->order) {
                $ticket->order->update([
                    'status' => OrderStatus::IN_KITCHEN->value,
                    'completed_at' => null,
                ]);
            }

            $ticket->load(['items.orderItem.meal', 'order', 'cook']);

            event(new KitchenTicketStatusUpdated($ticket, $previousStatus, KitchenTicketStatus::READY->value));

            return $ticket;
        });
    }
}
