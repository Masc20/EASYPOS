<?php

namespace App\Domains\Kitchen\Events;

use App\Domains\Kitchen\Models\KitchenTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenTicketStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public KitchenTicket $ticket,
        public string $previousStatus,
        public string $newStatus
    ) {}
}
