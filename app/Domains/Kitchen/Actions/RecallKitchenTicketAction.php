<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Events\KitchenTicketStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;

class RecallKitchenTicketAction
{
    /**
     * Recall a served ticket back to the ready queue.
     */
    public function execute(KitchenTicket $ticket): KitchenTicket
    {
        if (auth()->check() && ! auth()->user()->can('kitchen.prepare')) {
            abort(403, 'Unauthorized. Only kitchen staff can recall kitchen tickets.');
        }

        return DB::transaction(function () use ($ticket) {
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
