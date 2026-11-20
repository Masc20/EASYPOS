<?php

namespace App\Domains\PointOfSale\Livewire\Checkout;

use App\Domains\PointOfSale\Models\Order;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ReceiptPreview extends Component
{
    public bool $isOpen = false;

    #[Locked]
    public ?int $orderId = null;

    #[On('show-receipt')]
    public function loadOrder(int $orderId): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can('pos.access') && ! $user->can('reports.view'))) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isBranchScoped() && ($user->branch_id === null || $user->effectiveBranchId() <= 0)) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        $order = Order::find($orderId);
        if (! $order || ($user->isBranchScoped() && (int) $order->branch_id !== (int) $user->effectiveBranchId())) {
            abort(403, 'Unauthorized.');
        }

        $this->orderId = $order->id;
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
            $user = auth()->user();
            if ($user && ($user->can('pos.access') || $user->can('reports.view'))) {
                if (! $user->isBranchScoped() || ($user->branch_id !== null && $user->effectiveBranchId() > 0)) {
                    $query = Order::with(['items', 'payments', 'cashier', 'branch', 'kitchenTicket']);
                    if ($user->isBranchScoped()) {
                        $query->where('branch_id', $user->effectiveBranchId());
                    }
                    $order = $query->find($this->orderId);
                }
            }
        }

        return view('livewire.point-of-sale.checkout.receipt-preview', [
            'order' => $order,
        ]);
    }
}
