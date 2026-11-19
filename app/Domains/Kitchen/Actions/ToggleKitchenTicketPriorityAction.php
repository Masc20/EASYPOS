<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Actions\Concerns\AuthorizesKitchenActions;
use App\Domains\Kitchen\Models\KitchenTicket;

class ToggleKitchenTicketPriorityAction
{
    use AuthorizesKitchenActions;

    /**
     * Toggle priority between normal and rush.
     */
    public function execute(KitchenTicket $ticket): KitchenTicket
    {
        $this->authorizeKitchenAction($ticket, 'modify kitchen ticket priority');

        $newPriority = ($ticket->priority === 'rush') ? 'normal' : 'rush';
        $ticket->update(['priority' => $newPriority]);

        return $ticket;
    }
}
