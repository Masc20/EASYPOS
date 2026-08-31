<?php

namespace App\Domains\PointOfSale\Actions;

use App\Domains\PointOfSale\Events\SaleCompleted;
use App\Domains\PointOfSale\Models\Sale;

class ProcessCheckout
{
    public function handle(Sale $sale): Sale
    {
        event(new SaleCompleted($sale));

        return $sale;
    }
}
