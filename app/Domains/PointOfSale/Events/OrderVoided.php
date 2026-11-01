<?php

namespace App\Domains\PointOfSale\Events;

use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderVoided
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
        public User $manager,
        public string $reason
    ) {}
}
