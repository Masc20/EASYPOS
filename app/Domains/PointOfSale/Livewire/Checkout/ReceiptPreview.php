<?php

namespace App\Domains\PointOfSale\Livewire\Checkout;

use App\Domains\PointOfSale\Models\Order;
use Livewire\Attributes\On;
use Livewire\Component;

class ReceiptPreview extends Component
{
    public bool $isOpen = false;
    public ?int $orderId = null;

    #[On('show-receipt')]
    public function loadOrder(int $orderId): void
    {
        $this->orderId = $orderId;
        $this->isOpen = true;
        $this->dispatch('open-modal', 'receipt-modal');
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->orderId = null;
        $this->dispatch('close-modal', 'receipt-modal');
    }

    public function render()
    {
        $order = null;
        if ($this->orderId) {
            $order = Order::with(['items', 'payments', 'cashier', 'branch', 'kitchenTicket'])
                ->find($this->orderId);
        }

        return view('livewire.point-of-sale.checkout.receipt-preview', [
            'order' => $order,
        ]);
    }
}
