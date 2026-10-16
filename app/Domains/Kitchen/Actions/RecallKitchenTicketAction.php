<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Events\KitchenTicketStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicket;
use Illuminate\Support\Facades\DB;

class RecallKitchenTicketAction
{
    /**
     * Recall a served ticket back to the ready queue.
     */
    public function execute(KitchenTicket $ticket): KitchenTicket
    {
        return DB::transaction(function () use ($ticket) {
            $previousStatus = $ticket->status;

            $ticket->update([
                'status' => 'ready',
                'served_at' => null,
            ]);

            if ($ticket->order) {
                $ticket->order->update([
                    'status' => 'in_kitchen',
                    'completed_at' => null,
                ]);
            }

            $ticket->load(['items.orderItem.meal', 'order', 'cook']);

            event(new KitchenTicketStatusUpdated($ticket, $previousStatus, 'ready'));

            return $ticket;
        });
    }
}
