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
        $newPriority = ($ticket->priority === 'rush') ? 'normal' : 'rush';
        $ticket->update(['priority' => $newPriority]);

        return $ticket;
    }
}
