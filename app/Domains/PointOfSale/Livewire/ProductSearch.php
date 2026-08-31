<?php

namespace App\Domains\PointOfSale\Livewire;

use Livewire\Component;

class ProductSearch extends Component
{
    public string $query = '';

    public function render()
    {
        return view('livewire.point-of-sale.product-search');
    }
}
