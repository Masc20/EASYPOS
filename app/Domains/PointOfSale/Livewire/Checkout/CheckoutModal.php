<?php

namespace App\Domains\PointOfSale\Livewire\Checkout;

use Livewire\Component;

class CheckoutModal extends Component
{
    public bool $open = false;

    public function render()
    {
        return view('livewire.point-of-sale.checkout.checkout-modal');
    }
}
