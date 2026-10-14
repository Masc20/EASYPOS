<?php

namespace App\Domains\PointOfSale\Events;

use App\Domains\Kitchen\Models\KitchenTicket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenTicketDispatched
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public KitchenTicket $ticket
    ) {}
}
