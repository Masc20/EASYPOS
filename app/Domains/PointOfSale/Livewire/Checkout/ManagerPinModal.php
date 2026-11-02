<?php

namespace App\Domains\PointOfSale\Livewire\Checkout;

use App\Domains\PointOfSale\Actions\VoidOrderAction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ManagerPinModal extends Component
{
    public ?int $orderId = null;
    public ?string $errorMessage = null;

    #[On('open-manager-pin')]
    public function openModal(int $orderId): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->orderId = $orderId;
        $this->dispatch('open-modal', 'manager-pin-modal');
    }

    public function submitVoidRequest(array $data): bool
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if (! $this->orderId) {
            $this->errorMessage = 'No order selected for voiding.';
            return false;
        }

        if (blank($data['manager_id'] ?? null) || blank($data['manager_pin'] ?? null) || blank($data['reason'] ?? null)) {
            $this->errorMessage = 'All fields (Manager ID, PIN, Reason) are required.';
            $this->dispatch('pos-alert', [
                'message' => $this->errorMessage,
                'type' => 'error'
            ]);
            return false;
        }

        try {
            $action = new VoidOrderAction();
            // Execute synchronously so invalid credentials/PINs are caught immediately and shown to the user!
            $action->execute([
                'order_id'       => $this->orderId,
                'manager_id'     => $data['manager_id'],
                'manager_pin'    => $data['manager_pin'],
                'reason'         => $data['reason'],
                'cashier_id'     => Auth::id(),
                'skip_pin_check' => false,
            ]);

            $this->dispatch('close-modal', 'manager-pin-modal');
            $this->dispatch('order-voided', orderId: $this->orderId);
            $this->dispatch('pos-alert', [
                'message' => 'Order #' . $this->orderId . ' has been successfully voided and refunded.',
                'type' => 'success'
            ]);

            $this->orderId = null;
            return true;
        } catch (\Throwable $e) {
            // Captures wrong PINs, invalid manager IDs, or authorization errors and alerts the user!
            $this->errorMessage = $e->getMessage();
            $this->dispatch('pos-alert', [
                'message' => 'Void Failed: ' . $e->getMessage(),
                'type' => 'error'
            ]);
            return false;
        }
    }

    public function render()
    {
        return view('livewire.point-of-sale.checkout.manager-pin-modal');
    }
}
