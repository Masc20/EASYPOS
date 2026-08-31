<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\Stock;

class AdjustStock
{
    public function handle(Stock $stock, int $quantity): Stock
    {
        $stock->quantity += $quantity;

        return $stock;
    }
}
