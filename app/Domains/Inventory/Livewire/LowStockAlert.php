<?php

namespace App\Domains\Inventory\Livewire;

use Livewire\Component;

class LowStockAlert extends Component
{
    public function render()
    {
        return view('livewire.inventory.low-stock-alert');
    }
}
