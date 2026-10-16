<?php

namespace App\Domains\Kitchen\Events;

use App\Domains\Kitchen\Models\KitchenTicketItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenTicketItemStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public KitchenTicketItem $item,
        public string $newStatus
    ) {}
}
