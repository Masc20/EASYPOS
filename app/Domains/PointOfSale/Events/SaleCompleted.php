<?php

namespace App\Domains\PointOfSale\Events;

use App\Domains\PointOfSale\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Sale $sale) {}
}
