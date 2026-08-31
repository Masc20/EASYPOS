<?php

namespace App\Domains\Inventory\Events;

use App\Domains\Inventory\Models\Stock;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(public Stock $stock) {}
}
