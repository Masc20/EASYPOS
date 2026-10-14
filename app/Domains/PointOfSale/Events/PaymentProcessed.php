<?php

namespace App\Domains\PointOfSale\Events;

use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public Payment $payment
    ) {}
}
