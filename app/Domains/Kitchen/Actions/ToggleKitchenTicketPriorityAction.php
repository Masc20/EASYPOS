<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Models\KitchenTicket;

class ToggleKitchenTicketPriorityAction
{
    /**
     * Toggle priority between normal and rush.
     */
    public function execute(KitchenTicket $ticket): KitchenTicket
    {
        if (auth()->check() && ! auth()->user()->can('kitchen.prepare')) {
            abort(403, 'Unauthorized. Only kitchen staff can modify kitchen ticket priority.');
        }

        $newPriority = ($ticket->priority === 'rush') ? 'normal' : 'rush';
        $ticket->update(['priority' => $newPriority]);

        return $ticket;
    }
}
