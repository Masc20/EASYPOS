<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Actions\Concerns\AuthorizesKitchenActions;
use App\Domains\Kitchen\Events\KitchenTicketClaimed;
use App\Domains\Kitchen\Events\KitchenTicketStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Enums\KitchenTicketItemStatus;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;

class ClaimKitchenTicketAction
{
    use AuthorizesKitchenActions;

    /**
     * Assign cook, mark started, and transition ticket to in_prep.
     */
    public function execute(KitchenTicket $ticket, User $cook): KitchenTicket
    {
        $this->authorizeKitchenAction($ticket, 'claim kitchen tickets');

        return DB::transaction(function () use ($ticket, $cook) {
            $order = Order::whereKey($ticket->order_id)->lockForUpdate()->firstOrFail();
            $ticket = KitchenTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($order->status === OrderStatus::CANCELLED->value || $ticket->status === KitchenTicketStatus::CANCELLED->value) {
                throw new \InvalidArgumentException('Cancelled orders cannot be claimed.');
            }
            $previousStatus = $ticket->status;

            $ticket->update([
                'status' => KitchenTicketStatus::IN_PREP->value,
                'cook_id' => $cook->id,
                'started_at' => $ticket->started_at ?? now(),
            ]);

            // Mark any pending items as cooking
            $ticket->items()->where('status', KitchenTicketItemStatus::PENDING->value)->update([
                'status' => KitchenTicketItemStatus::COOKING->value,
            ]);

            $ticket->load(['items.orderItem.meal', 'order', 'cook']);

            event(new KitchenTicketClaimed($ticket, $cook));
            event(new KitchenTicketStatusUpdated($ticket, $previousStatus, KitchenTicketStatus::IN_PREP->value));

            return $ticket;
        });
    }
}
