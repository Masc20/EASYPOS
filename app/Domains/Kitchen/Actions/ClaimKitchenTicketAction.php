<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Events\KitchenTicketClaimed;
use App\Domains\Kitchen\Events\KitchenTicketStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicket;
use Illuminate\Support\Facades\DB;

class ClaimKitchenTicketAction
{
    /**
     * Assign cook, mark started, and transition ticket to in_prep.
     */
    public function execute(KitchenTicket $ticket, User $cook): KitchenTicket
    {
        if (auth()->check() && ! auth()->user()->can('kitchen.prepare')) {
            abort(403, 'Unauthorized. Only kitchen staff can claim kitchen tickets.');
        }

        return DB::transaction(function () use ($ticket, $cook) {
            $previousStatus = $ticket->status;

            $ticket->update([
                'status' => 'in_prep',
                'cook_id' => $cook->id,
                'started_at' => $ticket->started_at ?? now(),
            ]);

            // Mark any pending items as cooking
            $ticket->items()->where('status', 'pending')->update([
                'status' => 'cooking',
            ]);

            $ticket->load(['items.orderItem.meal', 'order', 'cook']);

            event(new KitchenTicketClaimed($ticket, $cook));
            event(new KitchenTicketStatusUpdated($ticket, $previousStatus, 'in_prep'));

            return $ticket;
        });
    }
}
