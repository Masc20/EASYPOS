<?php

namespace App\Domains\PointOfSale\Livewire\Checkout;

use Livewire\Component;

class CheckoutCart extends Component
{
    public array $items = [];

    public function render()
    {
        return view('livewire.point-of-sale.checkout.checkout-cart');
    }
}
