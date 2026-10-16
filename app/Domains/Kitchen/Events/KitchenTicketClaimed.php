<?php

namespace App\Domains\Kitchen\Events;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenTicketClaimed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public KitchenTicket $ticket,
        public User $cook
    ) {}
}
